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
function tg_send(PDO $pdo, string $html, ?string $buttonText = null, ?string $buttonUrl = null): bool {
    $token = setting_get($pdo, 'tg_token');
    $chat = setting_get($pdo, 'tg_chat_id');
    if (!$token || !$chat) return false;
    $p = ['chat_id' => $chat, 'text' => $html, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
    if ($buttonText && $buttonUrl) $p['reply_markup'] = ['inline_keyboard' => [[['text' => $buttonText, 'url' => $buttonUrl]]]];
    $r = tg_call($token, 'sendMessage', $p);
    return (bool) ($r['ok'] ?? false);
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
    tg_send($pdo, $msg, 'Reply in the inbox', $link);
}
