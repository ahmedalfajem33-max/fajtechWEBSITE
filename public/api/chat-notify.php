<?php
/**
 * Team alerts for the FAJTech live chat: email to every team member, Telegram, and
 * Web Push to the team inbox (/agent/, works as an iPhone home-screen app).
 * Loaded by chat.php only; does nothing when opened directly.
 */
declare(strict_types=1);
if (!defined('FAJCHAT')) { http_response_code(404); exit; }

// ── Small key/value settings table ──────────────────────────────────────────
function setting_get(PDO $pdo, string $k): ?string {
    $st = $pdo->prepare('SELECT v FROM chat_settings WHERE k = ?');
    $st->execute([$k]);
    $v = $st->fetchColumn();
    return $v === false ? null : (string) $v;
}
function setting_set(PDO $pdo, string $k, ?string $v): void {
    if ($v === null) { $pdo->prepare('DELETE FROM chat_settings WHERE k = ?')->execute([$k]); return; }
    $pdo->prepare('INSERT INTO chat_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)')->execute([$k, $v]);
}

// ── Run work after the HTTP response has been sent (keeps the chat fast) ────
$GLOBALS['faj_after'] = [];
function after_response(callable $fn): void { $GLOBALS['faj_after'][] = $fn; }
function run_after_response(): void {
    if (!$GLOBALS['faj_after']) return;
    if (function_exists('litespeed_finish_request')) litespeed_finish_request();
    elseif (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    ignore_user_abort(true);
    foreach ($GLOBALS['faj_after'] as $fn) { try { $fn(); } catch (Throwable $e) { error_log('FAJTech chat notify: ' . $e->getMessage()); } }
    $GLOBALS['faj_after'] = [];
}

function b64url(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }

// ── HTTP helper ─────────────────────────────────────────────────────────────
function http_json(string $url, ?array $post = null, int $timeout = 6): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 4]);
    if ($post !== null) {
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($post), CURLOPT_HTTPHEADER => ['Content-Type: application/json']]);
    }
    $out = curl_exec($ch);
    curl_close($ch);
    return is_string($out) ? (json_decode($out, true) ?: null) : null;
}

// ── Telegram ────────────────────────────────────────────────────────────────
function tg_call(string $token, string $method, ?array $params = null): ?array {
    if (!preg_match('/^\d+:[A-Za-z0-9_-]{30,}$/', $token)) return null;
    return http_json("https://api.telegram.org/bot{$token}/{$method}", $params);
}
/** Post to the team's Telegram chat. With $threadId, replies to this message are routed back to that website chat. */
function tg_send(PDO $pdo, string $html, ?string $buttonText = null, ?string $buttonUrl = null, ?int $threadId = null, $chatOverride = null): bool {
    $token = setting_get($pdo, 'tg_token');
    $chat = $chatOverride ?? setting_get($pdo, 'tg_chat_id');
    if (!$token || !$chat) return false;
    ensure_tg_webhook($pdo);
    $p = ['chat_id' => $chat, 'text' => $html, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
    if ($buttonText && $buttonUrl) $p['reply_markup'] = ['inline_keyboard' => [[['text' => $buttonText, 'url' => $buttonUrl]]]];
    $r = tg_call($token, 'sendMessage', $p);
    if (!($r['ok'] ?? false)) return false;
    if ($threadId) {
        $pdo->prepare('INSERT IGNORE INTO chat_tg_map (chat_id, message_id, thread_id) VALUES (?,?,?)')
            ->execute([(string) $r['result']['chat']['id'], (int) $r['result']['message_id'], $threadId]);
    }
    return true;
}
/** Register the webhook that lets the team answer visitors by replying in Telegram (once). */
function ensure_tg_webhook(PDO $pdo, bool $force = false): bool {
    $token = setting_get($pdo, 'tg_token');
    if (!$token) return false;
    if (!$force && setting_get($pdo, 'tg_webhook_secret')) return true;
    $secret = bin2hex(random_bytes(24));
    $r = tg_call($token, 'setWebhook', [
        'url' => 'https://fajtech.ca/api/chat.php?action=tg_webhook',
        'secret_token' => $secret,
        'allowed_updates' => ['message'],
    ]);
    if (!($r['ok'] ?? false)) { error_log('FAJTech chat: setWebhook failed ' . json_encode($r)); return false; }
    setting_set($pdo, 'tg_webhook_secret', $secret);
    return true;
}
function tg_drop_webhook(PDO $pdo): void {
    $token = setting_get($pdo, 'tg_token');
    if ($token) tg_call($token, 'deleteWebhook');
    setting_set($pdo, 'tg_webhook_secret', null);
}

// ── A team reply (from the inbox or from Telegram) ──────────────────────────
/** Saves the reply, reopens the chat, and emails it to the visitor if they have left the site. */
function post_agent_reply(PDO $pdo, array $t, string $agentName, string $text, string $mailTo, string $mailFrom, string $siteUrl): array {
    $pdo->prepare("INSERT INTO chat_messages (thread_id, sender, agent_name, body, created_at) VALUES (?, 'agent', ?, ?, UTC_TIMESTAMP())")->execute([$t['id'], $agentName, $text]);
    $mid = (int) $pdo->lastInsertId();
    $pdo->prepare("UPDATE chat_threads SET status = 'open', last_message_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$t['id']]);
    $away = strtotime($t['last_visitor_at'] . ' UTC') < time() - 120;
    if ($away) {
        $first = explode(' ', (string) $t['name'])[0];
        send_mail($t['email'], 'FAJTech replied to your chat',
            "Hi {$first},\n\n{$agentName} from FAJTech replied to your chat:\n\n{$text}\n\nContinue the conversation: {$siteUrl}/?chat={$t['token']}\nOr simply reply to this email.\n\nFAJTech · Managed IT & Cybersecurity\n",
            $mailFrom, $mailTo);
    }
    return ['id' => $mid, 'emailed' => $away];
}

/** Forward a visitor message to Telegram while the team is answering this chat from Telegram. */
function tg_forward_visitor(PDO $pdo, array $t, string $text): void {
    tg_send($pdo, '💬 <b>' . h((string) $t['name']) . "</b>:\n" . h(mb_substr($text, 0, 3000)) . "\n\n<i>Reply to this message to answer.</i>", null, null, (int) $t['id']);
}

/** Handle an update from Telegram (webhook). */
function tg_handle_update(PDO $pdo, array $u, string $mailTo, string $mailFrom, string $siteUrl): void {
    $msg = $u['message'] ?? null;
    if (!$msg || empty($msg['from']) || !empty($msg['from']['is_bot'])) return;
    $chatId = (string) $msg['chat']['id'];
    $teamChat = (string) setting_get($pdo, 'tg_chat_id');
    $isPrivate = ($msg['chat']['type'] ?? '') === 'private';
    if ($chatId !== $teamChat && !$isPrivate) return;
    $text = trim((string) ($msg['text'] ?? ''));
    $fromId = (string) $msg['from']['id'];
    $say = fn(string $html) => tg_send($pdo, $html, null, null, null, $chatId);

    // /link CODE: connect this Telegram account to a team member.
    if (preg_match('#^/link(?:@\w+)?\s+([A-Z0-9]{6})\b#i', $text, $m)) {
        $code = strtoupper($m[1]);
        $raw = setting_get($pdo, "tg_link_{$code}");
        $data = $raw ? json_decode($raw, true) : null;
        if (!$data || ($data['exp'] ?? 0) < time()) { $say('That link code is wrong or expired. Get a new one in the inbox: ☰ → Settings &amp; notifications.'); return; }
        $pdo->prepare('UPDATE chat_agents SET tg_user_id = NULL WHERE tg_user_id = ?')->execute([$fromId]);
        $pdo->prepare('UPDATE chat_agents SET tg_user_id = ? WHERE id = ?')->execute([$fromId, (int) $data['agent']]);
        setting_set($pdo, "tg_link_{$code}", null);
        $st = $pdo->prepare('SELECT name FROM chat_agents WHERE id = ?'); $st->execute([(int) $data['agent']]);
        $say('✅ Linked! Your Telegram replies to chat alerts will be sent to visitors as <b>' . h((string) $st->fetchColumn()) . '</b>.');
        return;
    }

    // A reply to one of our chat alerts → send it to that visitor.
    $re = $msg['reply_to_message'] ?? null;
    if (!$re) return;
    $st = $pdo->prepare('SELECT thread_id FROM chat_tg_map WHERE chat_id = ? AND message_id = ?');
    $st->execute([$chatId, (int) $re['message_id']]);
    $threadId = (int) $st->fetchColumn();
    if (!$threadId) return;
    $st = $pdo->prepare('SELECT id, name FROM chat_agents WHERE tg_user_id = ?');
    $st->execute([$fromId]);
    $agent = $st->fetch();
    if (!$agent) {
        $say('⚠️ Not sent. Link your Telegram first: in the inbox open ☰ → Settings &amp; notifications → <b>Link my Telegram</b>.');
        return;
    }
    if ($text === '') { $say('⚠️ Only text replies can be sent to the website chat.'); return; }
    $st = $pdo->prepare('SELECT * FROM chat_threads WHERE id = ?');
    $st->execute([$threadId]);
    $t = $st->fetch();
    if (!$t) return;
    $r = post_agent_reply($pdo, $t, (string) $agent['name'], mb_substr($text, 0, 5000), $mailTo, $mailFrom, $siteUrl);
    // Keep forwarding this visitor's messages to Telegram for the next 30 minutes.
    $pdo->prepare('UPDATE chat_threads SET tg_until = UTC_TIMESTAMP() + INTERVAL 30 MINUTE WHERE id = ?')->execute([$threadId]);
    $token = setting_get($pdo, 'tg_token');
    $ok = tg_call($token, 'setMessageReaction', ['chat_id' => $chatId, 'message_id' => (int) $msg['message_id'], 'reaction' => [['type' => 'emoji', 'emoji' => '👍']]]);
    if (!($ok['ok'] ?? false)) $say($r['emailed'] ? '✓ Sent (the visitor has left, so it was emailed too).' : '✓ Sent.');
}
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// ── Web Push (VAPID, no payload: the service worker fetches the details) ────
function vapid_keys(PDO $pdo): ?array {
    $pem = setting_get($pdo, 'vapid_private');
    $pub = setting_get($pdo, 'vapid_public');
    if ($pem && $pub) return ['private' => $pem, 'public' => $pub];
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if (!$key) return null;
    openssl_pkey_export($key, $pem);
    $d = openssl_pkey_get_details($key);
    $pub = b64url("\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT));
    setting_set($pdo, 'vapid_private', $pem);
    setting_set($pdo, 'vapid_public', $pub);
    return ['private' => $pem, 'public' => $pub];
}
/** ECDSA DER signature → raw 64-byte r||s (what JWT ES256 expects). */
function der_to_raw(string $der): string {
    $pos = 2;
    if (ord($der[1]) & 0x80) $pos += ord($der[1]) & 0x7f;
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        $pos++; // INTEGER tag
        $len = ord($der[$pos++]);
        $out .= str_pad(ltrim(substr($der, $pos, $len), "\0"), 32, "\0", STR_PAD_LEFT);
        $pos += $len;
    }
    return $out;
}
function vapid_header(array $keys, string $endpoint, string $subject): ?string {
    $p = parse_url($endpoint);
    $aud = $p['scheme'] . '://' . $p['host'];
    $data = b64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256'])) . '.' . b64url(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => $subject]));
    if (!openssl_sign($data, $sig, $keys['private'], OPENSSL_ALGO_SHA256)) return null;
    return 'vapid t=' . $data . '.' . b64url(der_to_raw($sig)) . ', k=' . $keys['public'];
}
/** Send a push to the given agents (null = everyone). Returns the number delivered. */
function push_agents(PDO $pdo, ?array $agentIds = null): int {
    $sql = 'SELECT id, endpoint FROM chat_push_subs';
    $params = [];
    if ($agentIds !== null) {
        if (!$agentIds) return 0;
        $sql .= ' WHERE agent_id IN (' . implode(',', array_fill(0, count($agentIds), '?')) . ')';
        $params = array_values($agentIds);
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $subs = $st->fetchAll();
    if (!$subs) return 0;
    $keys = vapid_keys($pdo);
    if (!$keys) return 0;
    $mh = curl_multi_init();
    $handles = [];
    foreach ($subs as $s) {
        $auth = vapid_header($keys, $s['endpoint'], 'mailto:info@fajtech.ca');
        if (!$auth) continue;
        $ch = curl_init($s['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => '', CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['TTL: 3600', 'Urgency: high', 'Authorization: ' . $auth, 'Content-Length: 0'],
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[(int) $s['id']] = $ch;
    }
    do { $status = curl_multi_exec($mh, $running); if ($running) curl_multi_select($mh, 1.0); } while ($running && $status === CURLM_OK);
    $sent = 0;
    $gone = [];
    foreach ($handles as $id => $ch) {
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code >= 200 && $code < 300) $sent++;
        elseif (in_array($code, [403, 404, 410], true)) $gone[] = $id;
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    if ($gone) $pdo->prepare('DELETE FROM chat_push_subs WHERE id IN (' . implode(',', array_fill(0, count($gone), '?')) . ')')->execute($gone);
    return $sent;
}

// ── What happens when something needs the team's attention ─────────────────
/**
 * $kind: 'new' (chat started), 'reopened' (visitor wrote in a closed chat), 'message' (visitor wrote again).
 */
function notify_team(PDO $pdo, string $kind, array $t, string $text, string $topics, string $mailTo, string $mailFrom, string $siteUrl): void {
    $link = "{$siteUrl}/agent/#chat-{$t['id']}";
    $name = (string) $t['name'];
    $email = (string) $t['email'];

    // 1) Email: info@ (or the configured address) + every team member who has email alerts on.
    if ($kind !== 'message') {
        $subject = ($kind === 'new' ? "New website chat from {$name}" : "Chat reopened by {$name}") . ($topics !== '' ? " ({$topics})" : '');
        $body = ($kind === 'new' ? "New chat on fajtech.ca" : "{$name} wrote again in a closed chat") . "\n\n"
            . "Name: {$name}\nEmail: {$email}\n" . ($topics !== '' ? "Team: {$topics}\n" : '') . "Page: {$t['page']}\n\n"
            . "Message:\n{$text}\n\nReply in the team inbox: {$link}\n";
        $to = [strtolower($mailTo) => $mailTo];
        foreach ($pdo->query('SELECT email FROM chat_agents WHERE notify_email = 1') as $a) $to[strtolower($a['email'])] = $a['email'];
        foreach ($to as $addr) send_mail($addr, $subject, $body, $mailFrom, $email);
    }

    // 2) Push to the inbox app on phones/computers.
    push_agents($pdo);

    // 3) Telegram.
    $title = ['new' => '💬 New website chat', 'reopened' => '🔁 Chat reopened', 'message' => '💬 New message'][$kind];
    $msg = "<b>{$title}</b>\n<b>" . h($name) . '</b> · ' . h($email)
        . ($topics !== '' ? "\nTeam: " . h($topics) : '')
        . "\n\n" . h(mb_substr($text, 0, 1500));
    tg_send($pdo, $msg . "\n\n<i>Reply to this message to answer here, or use the inbox.</i>", 'Open in the inbox', $link, (int) $t['id']);
}
