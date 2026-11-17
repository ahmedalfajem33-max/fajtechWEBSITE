<?php
/**
 * FAJTech live chat — visitor widget + private team inbox (/agent/).
 *
 * Storage: MySQL on Hostinger (tables are created automatically on first use).
 * Settings: fajtech-chat.php OUTSIDE public_html (database login), plus mail settings from
 * fajtech-config.php. Neither file is ever committed. See README.md → "Live chat".
 *
 * Visitor actions (no login, identified by a random thread token kept in the browser):
 *   status, start, send, poll
 * Team actions (PHP session login):
 *   login, logout, me, password, list, thread, reply, close, reopen, agents, agent_add, agent_remove,
 *   prefs, prefs_save, push_key, push_sub, push_unsub, push_test, push_preview,
 *   tg_me, tg_link_code, tg_unlink (any member), tg_status, tg_connect, tg_test, tg_disconnect (admins)
 * Telegram webhook (no login, checked with a secret header): tg_webhook
 * Team alerts (email to every member, Telegram, Web Push) live in chat-notify.php.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

function respond(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    if (function_exists('run_after_response')) run_after_response();
    exit;
}
function fail(int $code, string $msg): void { respond($code, ['ok' => false, 'error' => $msg]); }

// ── Settings ────────────────────────────────────────────────────────────────
$config = [];
foreach ([dirname(__DIR__, 2), dirname(__DIR__, 3)] as $dir) {
    if (is_readable("$dir/fajtech-config.php")) { $config = (array) require "$dir/fajtech-config.php"; break; }
}
$chatCfg = [];
foreach ([dirname(__DIR__, 2), dirname(__DIR__, 3)] as $dir) {
    if (is_readable("$dir/fajtech-chat.php")) { $chatCfg = (array) require "$dir/fajtech-chat.php"; break; }
}
$chatCfg += (array) ($config['chat'] ?? []);
$mailTo   = $chatCfg['notify'] ?? ($config['mail_to'] ?? 'info@fajtech.ca');
$mailFrom = $config['mail_from'] ?? 'website@fajtech.ca';
$siteUrl  = 'https://fajtech.ca';

if (empty($chatCfg['db_name'])) fail(503, 'Chat is not set up yet.');

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $chatCfg['db_host'] ?? '127.0.0.1', (int) ($chatCfg['db_port'] ?? 3306), $chatCfg['db_name']),
        $chatCfg['db_user'], $chatCfg['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $pdo->exec("SET time_zone = '+00:00'");
} catch (Throwable $e) {
    error_log('FAJTech chat DB: ' . $e->getMessage());
    fail(503, 'Chat is temporarily unavailable.');
}

// ── Schema (idempotent) ─────────────────────────────────────────────────────
function ensure_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_agents (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(80) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        pass_hash VARCHAR(255) NOT NULL,
        is_admin TINYINT(1) NOT NULL DEFAULT 0,
        must_change TINYINT(1) NOT NULL DEFAULT 0,
        last_seen_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_threads (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        token CHAR(64) NOT NULL UNIQUE,
        name VARCHAR(80) NOT NULL,
        email VARCHAR(150) NOT NULL,
        page VARCHAR(300) NOT NULL DEFAULT '',
        ip VARCHAR(45) NOT NULL DEFAULT '',
        status ENUM('open','closed') NOT NULL DEFAULT 'open',
        agent_seen_id INT UNSIGNED NOT NULL DEFAULT 0,
        last_visitor_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_message_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY status_last (status, last_message_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        thread_id INT UNSIGNED NOT NULL,
        sender ENUM('visitor','agent','system') NOT NULL,
        agent_name VARCHAR(80) NULL,
        body TEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY thread_id (thread_id, id),
        CONSTRAINT fk_chat_thread FOREIGN KEY (thread_id) REFERENCES chat_threads(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // v2: settings, push subscriptions, per-member email alerts
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_settings (
        k VARCHAR(50) PRIMARY KEY,
        v TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_push_subs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        agent_id INT UNSIGNED NOT NULL,
        endpoint VARCHAR(700) NOT NULL,
        endpoint_hash CHAR(64) NOT NULL UNIQUE,
        device VARCHAR(120) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY agent_id (agent_id),
        CONSTRAINT fk_push_agent FOREIGN KEY (agent_id) REFERENCES chat_agents(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $has = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chat_agents' AND COLUMN_NAME = 'notify_email'")->fetchColumn();
    if (!$has) $pdo->exec("ALTER TABLE chat_agents ADD COLUMN notify_email TINYINT(1) NOT NULL DEFAULT 1");
    // v3: answer from Telegram
    $col = fn(string $t, string $c) => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = '$c'")->fetchColumn();
    if (!$col('chat_agents', 'tg_user_id')) $pdo->exec("ALTER TABLE chat_agents ADD COLUMN tg_user_id VARCHAR(32) NULL, ADD KEY tg_user_id (tg_user_id)");
    if (!$col('chat_threads', 'tg_until')) $pdo->exec("ALTER TABLE chat_threads ADD COLUMN tg_until DATETIME NULL");
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_tg_map (
        chat_id VARCHAR(32) NOT NULL,
        message_id BIGINT NOT NULL,
        thread_id INT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (chat_id, message_id),
        KEY thread_id (thread_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
$schemaFlag = sys_get_temp_dir() . '/faj_chat_schema_v3_' . md5((string) $chatCfg['db_name']);
if (!is_file($schemaFlag)) { ensure_schema($pdo); @touch($schemaFlag); }

// ── Helpers ─────────────────────────────────────────────────────────────────
$in = $_POST;
if (str_starts_with((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
    $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
}
$action = (string) ($_GET['action'] ?? $in['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ip     = $_SERVER['REMOTE_ADDR'] ?? '';

function str_in(array $in, string $k, int $max): string {
    $v = trim(str_replace(["\0", "\r"], '', (string) ($in[$k] ?? '')));
    return mb_substr($v, 0, $max);
}
/** Allow $limit hits per $window seconds for $key; false when over the limit. */
function rate_ok(string $key, int $limit, int $window): bool {
    $f = sys_get_temp_dir() . '/faj_chat_rl_' . md5($key);
    $now = time();
    $hits = array_values(array_filter(json_decode((string) @file_get_contents($f), true) ?: [], fn($t) => $t > $now - $window));
    if (count($hits) >= $limit) return false;
    $hits[] = $now;
    @file_put_contents($f, json_encode($hits), LOCK_EX);
    return true;
}
define('FAJMAILER', true);
require_once __DIR__ . '/mailer.php';
/** Email via our Exchange server (see mailer.php); $from is only used by the PHP mail() fallback. */
function send_mail(string $to, string $subject, string $body, string $from, string $replyTo = ''): void {
    global $config;
    faj_send_mail(['mail_from' => $from] + (array) $config, $to, $subject, $body, $replyTo);
}
function agents_online(PDO $pdo): bool {
    return (bool) $pdo->query("SELECT 1 FROM chat_agents WHERE last_seen_at > UTC_TIMESTAMP() - INTERVAL 3 MINUTE LIMIT 1")->fetchColumn();
}
function iso(?string $dt): ?string { return $dt ? str_replace(' ', 'T', $dt) . 'Z' : null; }
function msg_out(array $m): array {
    return ['id' => (int) $m['id'], 'from' => $m['sender'], 'agent' => $m['agent_name'], 'body' => $m['body'], 'at' => iso($m['created_at'])];
}
function thread_by_token(PDO $pdo, string $token): array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) fail(404, 'Conversation not found.');
    $st = $pdo->prepare('SELECT * FROM chat_threads WHERE token = ?');
    $st->execute([$token]);
    $t = $st->fetch();
    if (!$t) fail(404, 'Conversation not found.');
    return $t;
}
function require_post(string $method): void { if ($method !== 'POST') fail(405, 'Method not allowed.'); }

define('FAJCHAT', true);
require __DIR__ . '/chat-notify.php';
define('FAJLEADS', true);
require_once __DIR__ . '/leads.php';

// ── Visitor actions ─────────────────────────────────────────────────────────
switch ($action) {
case 'tg_webhook':
    // Telegram → website: a team member replied to a chat alert in Telegram.
    $secret = setting_get($pdo, 'tg_webhook_secret');
    if (!$secret || !hash_equals($secret, (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? ''))) fail(403, 'Forbidden.');
    try { tg_handle_update($pdo, $in, $mailTo, $mailFrom, $siteUrl); } catch (Throwable $e) { error_log('FAJTech chat tg_webhook: ' . $e->getMessage()); }
    respond(200, ['ok' => true]);

case 'status':
    respond(200, ['ok' => true, 'online' => agents_online($pdo)]);

case 'unsubscribe':
    // Public: the personal link fajtech.ca/unsubscribe/?t=... (CASL: works without signing in).
    require_post($method);
    if (!rate_ok("unsub:$ip", 20, 600)) fail(429, 'Too many requests. Please try again in a few minutes.');
    $email = faj_lead_unsubscribe($pdo, str_in($in, 't', 32), $ip);
    if ($email === null) fail(404, 'This unsubscribe link is not valid. Email info@fajtech.ca and we will remove you.');
    $masked = preg_replace('/^(.).*(@.*)$/', '$1•••$2', $email);
    respond(200, ['ok' => true, 'email' => $masked]);

case 'start':
    require_post($method);
    if (!empty($in['website'])) respond(200, ['ok' => true, 'token' => str_repeat('0', 64), 'messages' => []]); // spam trap
    if (!rate_ok("start:$ip", 4, 900)) fail(429, 'Too many new chats from your connection. Please wait a few minutes.');
    $name = str_in($in, 'name', 80);
    $email = str_in($in, 'email', 150);
    $text = str_in($in, 'message', 3000);
    $page = str_in($in, 'page', 300);
    $topics = str_in($in, 'topics', 300);
    if ($name === '' || $text === '') fail(422, 'Please enter your name and your question.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail(422, 'Please enter a valid email address so we can reply.');
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO chat_threads (token, name, email, page, ip, last_visitor_at, last_message_at, created_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())')
        ->execute([$token, $name, $email, $page, $ip]);
    $tid = (int) $pdo->lastInsertId();
    if ($topics !== '') $pdo->prepare("INSERT INTO chat_messages (thread_id, sender, body, created_at) VALUES (?, 'system', ?, UTC_TIMESTAMP())")->execute([$tid, "Wants to talk to: {$topics}"]);
    $pdo->prepare("INSERT INTO chat_messages (thread_id, sender, body, created_at) VALUES (?, 'visitor', ?, UTC_TIMESTAMP())")->execute([$tid, $text]);
    $thread = ['id' => $tid, 'name' => $name, 'email' => $email, 'page' => $page];
    faj_lead_save_safe($pdo, ['email' => $email, 'name' => $name, 'source' => 'chat', 'page' => $page, 'ip' => $ip, 'consent' => false]);
    after_response(fn() => notify_team($pdo, 'new', $thread, $text, $topics, $mailTo, $mailFrom, $siteUrl));
    $st = $pdo->prepare('SELECT * FROM chat_messages WHERE thread_id = ? ORDER BY id');
    $st->execute([$tid]);
    respond(200, ['ok' => true, 'token' => $token, 'online' => agents_online($pdo), 'messages' => array_map('msg_out', $st->fetchAll())]);

case 'send':
    require_post($method);
    $t = thread_by_token($pdo, str_in($in, 'token', 64));
    if (!rate_ok("send:{$t['id']}", 30, 600)) fail(429, 'You are sending messages too quickly. Please wait a moment.');
    $text = str_in($in, 'message', 3000);
    if ($text === '') fail(422, 'Please type a message.');
    $pdo->prepare("INSERT INTO chat_messages (thread_id, sender, body, created_at) VALUES (?, 'visitor', ?, UTC_TIMESTAMP())")->execute([$t['id'], $text]);
    $id = (int) $pdo->lastInsertId();
    $wasClosed = $t['status'] === 'closed';
    $pdo->prepare("UPDATE chat_threads SET status = 'open', last_visitor_at = UTC_TIMESTAMP(), last_message_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$t['id']]);
    $viaTelegram = !empty($t['tg_until']) && strtotime($t['tg_until'] . ' UTC') > time();
    if ($viaTelegram) {
        // The team is answering this chat from Telegram: forward every message there right away.
        after_response(function () use ($pdo, $t, $text) { tg_forward_visitor($pdo, $t, $text); push_agents($pdo); });
    } elseif ($wasClosed) {
        after_response(fn() => notify_team($pdo, 'reopened', $t, $text, '', $mailTo, $mailFrom, $siteUrl));
    } elseif (!agents_online($pdo) && rate_ok("notify:{$t['id']}", 1, 120)) {
        // Nobody has the inbox open: ping phones/Telegram (at most every 2 minutes per chat).
        after_response(fn() => notify_team($pdo, 'message', $t, $text, '', $mailTo, $mailFrom, $siteUrl));
    } elseif (rate_ok("push:{$t['id']}", 1, 60)) {
        after_response(fn() => push_agents($pdo));
    }
    respond(200, ['ok' => true, 'id' => $id]);

case 'poll':
    $t = thread_by_token($pdo, str_in($in + $_GET, 'token', 64));
    $after = max(0, (int) ($in['after'] ?? $_GET['after'] ?? 0));
    $pdo->prepare('UPDATE chat_threads SET last_visitor_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$t['id']]);
    $st = $pdo->prepare('SELECT * FROM chat_messages WHERE thread_id = ? AND id > ? ORDER BY id LIMIT 200');
    $st->execute([$t['id'], $after]);
    respond(200, ['ok' => true, 'status' => $t['status'], 'name' => $t['name'], 'online' => agents_online($pdo), 'messages' => array_map('msg_out', $st->fetchAll())]);
}

// ── Team actions (logged-in agents) ─────────────────────────────────────────
session_name('fajchat');
session_set_cookie_params(['lifetime' => 60 * 60 * 24 * 14, 'path' => '/api/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 14));
session_start();

// Team requests must come from our own pages (blocks cross-site form posts).
if ($method === 'POST' && ($_SERVER['HTTP_X_FAJCHAT'] ?? '') !== '1') fail(403, 'Forbidden.');

function current_agent(PDO $pdo): array {
    $id = (int) ($_SESSION['agent_id'] ?? 0);
    if (!$id) fail(401, 'Please sign in.');
    $st = $pdo->prepare('SELECT id, name, email, is_admin, must_change, notify_email FROM chat_agents WHERE id = ?');
    $st->execute([$id]);
    $a = $st->fetch();
    if (!$a) { $_SESSION = []; fail(401, 'Please sign in.'); }
    $pdo->prepare('UPDATE chat_agents SET last_seen_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
    return $a;
}
function agent_out(array $a): array {
    return ['id' => (int) $a['id'], 'name' => $a['name'], 'email' => $a['email'], 'admin' => (bool) $a['is_admin'], 'mustChange' => (bool) $a['must_change'], 'notifyEmail' => (bool) ($a['notify_email'] ?? true)];
}
function strong_enough(string $p): bool { return mb_strlen($p) >= 10; }

switch ($action) {
case 'login':
    require_post($method);
    if (!rate_ok("login:$ip", 8, 900)) fail(429, 'Too many sign-in attempts. Please wait 15 minutes.');
    $st = $pdo->prepare('SELECT * FROM chat_agents WHERE email = ?');
    $st->execute([mb_strtolower(str_in($in, 'email', 150))]);
    $a = $st->fetch();
    if (!$a || !password_verify((string) ($in['password'] ?? ''), $a['pass_hash'])) fail(401, 'Wrong email or password.');
    session_regenerate_id(true);
    $_SESSION['agent_id'] = (int) $a['id'];
    respond(200, ['ok' => true, 'agent' => agent_out($a)]);

case 'logout':
    require_post($method);
    $_SESSION = [];
    session_destroy();
    respond(200, ['ok' => true]);

case 'me':
    respond(200, ['ok' => true, 'agent' => agent_out(current_agent($pdo))]);

case 'password':
    require_post($method);
    $a = current_agent($pdo);
    $st = $pdo->prepare('SELECT pass_hash FROM chat_agents WHERE id = ?');
    $st->execute([$a['id']]);
    if (!password_verify((string) ($in['current'] ?? ''), (string) $st->fetchColumn())) fail(422, 'Your current password is wrong.');
    $new = (string) ($in['new'] ?? '');
    if (!strong_enough($new)) fail(422, 'Use at least 10 characters for the new password.');
    $pdo->prepare('UPDATE chat_agents SET pass_hash = ?, must_change = 0 WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $a['id']]);
    respond(200, ['ok' => true]);

case 'list':
    current_agent($pdo);
    $status = ($_GET['status'] ?? 'open') === 'closed' ? 'closed' : 'open';
    $st = $pdo->prepare("SELECT t.id, t.name, t.email, t.page, t.status, t.last_message_at, t.last_visitor_at, t.created_at,
            (SELECT COUNT(*) FROM chat_messages m WHERE m.thread_id = t.id AND m.sender = 'visitor' AND m.id > t.agent_seen_id) AS unread,
            (SELECT m.body FROM chat_messages m WHERE m.thread_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_body,
            (SELECT m.sender FROM chat_messages m WHERE m.thread_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_from
        FROM chat_threads t WHERE t.status = ? ORDER BY t.last_message_at DESC LIMIT 200");
    $st->execute([$status]);
    $rows = array_map(fn($r) => [
        'id' => (int) $r['id'], 'name' => $r['name'], 'email' => $r['email'], 'page' => $r['page'], 'status' => $r['status'],
        'unread' => (int) $r['unread'], 'last' => mb_substr((string) $r['last_body'], 0, 140), 'lastFrom' => $r['last_from'],
        'lastAt' => iso($r['last_message_at']), 'visitorSeenAt' => iso($r['last_visitor_at']), 'createdAt' => iso($r['created_at']),
    ], $st->fetchAll());
    $openUnread = (int) $pdo->query("SELECT COUNT(*) FROM chat_threads t WHERE t.status = 'open' AND EXISTS (SELECT 1 FROM chat_messages m WHERE m.thread_id = t.id AND m.sender = 'visitor' AND m.id > t.agent_seen_id)")->fetchColumn();
    respond(200, ['ok' => true, 'threads' => $rows, 'openUnread' => $openUnread]);

case 'thread':
    current_agent($pdo);
    $id = (int) ($_GET['id'] ?? 0);
    $after = max(0, (int) ($_GET['after'] ?? 0));
    $st = $pdo->prepare('SELECT * FROM chat_threads WHERE id = ?');
    $st->execute([$id]);
    $t = $st->fetch();
    if (!$t) fail(404, 'Conversation not found.');
    $st = $pdo->prepare('SELECT * FROM chat_messages WHERE thread_id = ? AND id > ? ORDER BY id LIMIT 500');
    $st->execute([$id, $after]);
    $msgs = $st->fetchAll();
    $maxVisitor = 0;
    foreach ($msgs as $m) if ($m['sender'] === 'visitor') $maxVisitor = max($maxVisitor, (int) $m['id']);
    if ($maxVisitor > (int) $t['agent_seen_id']) $pdo->prepare('UPDATE chat_threads SET agent_seen_id = ? WHERE id = ?')->execute([$maxVisitor, $id]);
    respond(200, ['ok' => true, 'thread' => [
        'id' => (int) $t['id'], 'name' => $t['name'], 'email' => $t['email'], 'page' => $t['page'], 'status' => $t['status'],
        'createdAt' => iso($t['created_at']), 'visitorSeenAt' => iso($t['last_visitor_at']),
    ], 'messages' => array_map('msg_out', $msgs)]);

case 'reply':
    require_post($method);
    $a = current_agent($pdo);
    $id = (int) ($in['id'] ?? 0);
    $text = str_in($in, 'message', 5000);
    if ($text === '') fail(422, 'Please type a reply.');
    $st = $pdo->prepare('SELECT * FROM chat_threads WHERE id = ?');
    $st->execute([$id]);
    $t = $st->fetch();
    if (!$t) fail(404, 'Conversation not found.');
    $r = post_agent_reply($pdo, $t, $a['name'], $text, $mailTo, $mailFrom, $siteUrl);
    respond(200, ['ok' => true, 'id' => $r['id'], 'emailed' => $r['emailed']]);

case 'close':
case 'reopen':
    require_post($method);
    current_agent($pdo);
    $pdo->prepare('UPDATE chat_threads SET status = ? WHERE id = ?')->execute([$action === 'close' ? 'closed' : 'open', (int) ($in['id'] ?? 0)]);
    respond(200, ['ok' => true]);

case 'agents':
    $a = current_agent($pdo);
    if (!$a['is_admin']) fail(403, 'Only admins can manage the team.');
    $rows = $pdo->query('SELECT id, name, email, is_admin, must_change, notify_email, last_seen_at FROM chat_agents ORDER BY name')->fetchAll();
    respond(200, ['ok' => true, 'agents' => array_map(fn($r) => agent_out($r) + ['lastSeenAt' => iso($r['last_seen_at'])], $rows)]);

case 'agent_add':
    require_post($method);
    $a = current_agent($pdo);
    if (!$a['is_admin']) fail(403, 'Only admins can manage the team.');
    $name = str_in($in, 'name', 80);
    $email = mb_strtolower(str_in($in, 'email', 150));
    $pass = (string) ($in['password'] ?? '');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) fail(422, 'Enter a name and a valid email.');
    if (!strong_enough($pass)) fail(422, 'Use a temporary password of at least 10 characters.');
    try {
        $pdo->prepare('INSERT INTO chat_agents (name, email, pass_hash, is_admin, must_change) VALUES (?,?,?,?,1)')
            ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), !empty($in['admin']) ? 1 : 0]);
    } catch (PDOException $e) { fail(422, 'Someone with that email is already on the team.'); }
    respond(200, ['ok' => true]);

case 'agent_remove':
    require_post($method);
    $a = current_agent($pdo);
    if (!$a['is_admin']) fail(403, 'Only admins can manage the team.');
    $id = (int) ($in['id'] ?? 0);
    if ($id === (int) $a['id']) fail(422, "You can't remove yourself.");
    $pdo->prepare('DELETE FROM chat_agents WHERE id = ?')->execute([$id]);
    respond(200, ['ok' => true]);

case 'prefs_save':
    require_post($method);
    $a = current_agent($pdo);
    $name = str_in($in, 'name', 80);
    if ($name === '') fail(422, 'Please enter the name visitors will see.');
    $pdo->prepare('UPDATE chat_agents SET name = ?, notify_email = ? WHERE id = ?')->execute([$name, !empty($in['notifyEmail']) ? 1 : 0, $a['id']]);
    respond(200, ['ok' => true]);

case 'push_key':
    current_agent($pdo);
    $keys = vapid_keys($pdo);
    if (!$keys) fail(503, 'Push notifications are not available on this server.');
    respond(200, ['ok' => true, 'key' => $keys['public']]);

case 'push_sub':
    require_post($method);
    $a = current_agent($pdo);
    $endpoint = str_in($in, 'endpoint', 700);
    if (!preg_match('#^https://[a-z0-9.-]+/#i', $endpoint)) fail(422, 'Invalid push subscription.');
    $pdo->prepare('INSERT INTO chat_push_subs (agent_id, endpoint, endpoint_hash, device) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE agent_id = VALUES(agent_id), device = VALUES(device)')
        ->execute([$a['id'], $endpoint, hash('sha256', $endpoint), str_in($in, 'device', 120)]);
    respond(200, ['ok' => true]);

case 'push_unsub':
    require_post($method);
    $a = current_agent($pdo);
    $pdo->prepare('DELETE FROM chat_push_subs WHERE agent_id = ? AND endpoint_hash = ?')->execute([$a['id'], hash('sha256', str_in($in, 'endpoint', 700))]);
    respond(200, ['ok' => true]);

case 'push_test':
    require_post($method);
    $a = current_agent($pdo);
    $_SESSION['push_test'] = time();
    $n = push_agents($pdo, [(int) $a['id']]);
    if (!$n) fail(422, 'No device is set up for notifications yet. Tap "Turn on notifications" first.');
    respond(200, ['ok' => true, 'sent' => $n]);

case 'push_preview':
    // Called by the service worker when a push arrives, to show what's new.
    $a = current_agent($pdo);
    if (time() - (int) ($_SESSION['push_test'] ?? 0) < 60) {
        unset($_SESSION['push_test']);
        respond(200, ['ok' => true, 'title' => 'FAJTech Chat', 'body' => 'Notifications are working on this device. ✅', 'url' => '/agent/', 'tag' => 'fajchat-test']);
    }
    $st = $pdo->query("SELECT t.id, t.name,
            (SELECT m.body FROM chat_messages m WHERE m.thread_id = t.id AND m.sender = 'visitor' ORDER BY m.id DESC LIMIT 1) AS body,
            (SELECT COUNT(*) FROM chat_messages m WHERE m.thread_id = t.id AND m.sender = 'visitor' AND m.id > t.agent_seen_id) AS unread
        FROM chat_threads t WHERE t.status = 'open'
        HAVING unread > 0 ORDER BY t.last_message_at DESC LIMIT 1");
    $t = $st->fetch();
    if (!$t) respond(200, ['ok' => true, 'title' => 'FAJTech Chat', 'body' => 'You have a new chat message.', 'url' => '/agent/', 'tag' => 'fajchat']);
    respond(200, ['ok' => true, 'title' => "{$t['name']} on fajtech.ca", 'body' => mb_substr((string) $t['body'], 0, 180), 'url' => "/agent/#chat-{$t['id']}", 'tag' => "fajchat-{$t['id']}"]);

case 'tg_status':
    $a = current_agent($pdo);
    if (!$a['is_admin']) fail(403, 'Only admins can change Telegram alerts.');
    if (setting_get($pdo, 'tg_token') && setting_get($pdo, 'tg_chat_id')) ensure_tg_webhook($pdo);
    respond(200, ['ok' => true, 'connected' => (bool) (setting_get($pdo, 'tg_token') && setting_get($pdo, 'tg_chat_id')),
        'bot' => setting_get($pdo, 'tg_bot'), 'chat' => setting_get($pdo, 'tg_chat_title'), 'hasToken' => (bool) setting_get($pdo, 'tg_token')]);

case 'tg_connect':
    require_post($method);
    $a = current_agent($pdo);
    if (!$a['is_admin']) fail(403, 'Only admins can change Telegram alerts.');
    $token = str_in($in, 'token', 100) ?: (string) setting_get($pdo, 'tg_token');
    $me = tg_call($token, 'getMe');
    if (!($me['ok'] ?? false)) fail(422, "That bot token didn't work. Copy it again from @BotFather (it looks like 123456789:AAH…).");
    if (setting_get($pdo, 'tg_token')) tg_drop_webhook($pdo); // getUpdates only works without a webhook
    tg_call($token, 'deleteWebhook');
    setting_set($pdo, 'tg_token', $token);
    setting_set($pdo, 'tg_bot', '@' . $me['result']['username']);
    $up = tg_call($token, 'getUpdates', ['allowed_updates' => ['message', 'my_chat_member', 'channel_post']]);
    $chat = null;
    foreach (array_reverse($up['result'] ?? []) as $u) {
        $c = $u['message']['chat'] ?? $u['my_chat_member']['chat'] ?? $u['channel_post']['chat'] ?? null;
        $leftNow = isset($u['my_chat_member']) && in_array($u['my_chat_member']['new_chat_member']['status'] ?? '', ['left', 'kicked'], true);
        if ($c && !$leftNow) { $chat = $c; break; }
    }
    if (!$chat) respond(200, ['ok' => true, 'connected' => false, 'bot' => '@' . $me['result']['username'],
        'next' => 'Bot found. Now add it to your Telegram group (or open the bot and press Start), send any message there, then press "Connect" again.']);
    $title = $chat['title'] ?? trim(($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? '')) ?: 'Telegram chat';
    setting_set($pdo, 'tg_chat_id', (string) $chat['id']);
    setting_set($pdo, 'tg_chat_title', $title);
    setting_set($pdo, 'tg_owner_agent', (string) $a['id']);
    ensure_tg_webhook($pdo, true);
    tg_send($pdo, "✅ <b>FAJTech website chat is connected.</b>\nNew chats from fajtech.ca will be posted here.", 'Open the inbox', 'https://fajtech.ca/agent/');
    respond(200, ['ok' => true, 'connected' => true, 'bot' => '@' . $me['result']['username'], 'chat' => $title]);

case 'contacts':
    $a = current_agent($pdo);
    if (!$a['is_admin']) fail(403, 'Only admins can see the contacts list.');
    faj_leads_schema($pdo);
    $q = str_in($_GET, 'q', 100);
    $filter = (string) ($_GET['filter'] ?? 'all');
    $where = ['1=1']; $args = [];
    if ($q !== '') { $where[] = '(email LIKE ? OR name LIKE ? OR company LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%"); }
    if ($filter === 'consent') $where[] = 'consent = 1 AND unsubscribed_at IS NULL';
    elseif ($filter === 'noconsent') $where[] = 'consent = 0 AND unsubscribed_at IS NULL';
    elseif ($filter === 'unsub') $where[] = 'unsubscribed_at IS NOT NULL';
    $st = $pdo->prepare('SELECT id, email, name, company, phone, sources, interactions, first_seen, last_seen, consent, consent_at, consent_source, consent_page, unsubscribed_at FROM contacts WHERE ' . implode(' AND ', $where) . ' ORDER BY last_seen DESC LIMIT 1000');
    $st->execute($args);
    $rows = array_map(fn($r) => [
        'id' => (int) $r['id'], 'email' => $r['email'], 'name' => $r['name'], 'company' => $r['company'], 'phone' => $r['phone'],
        'sources' => $r['sources'] === '' ? [] : explode(',', $r['sources']), 'interactions' => (int) $r['interactions'],
        'firstSeen' => iso($r['first_seen']), 'lastSeen' => iso($r['last_seen']),
        'consent' => (bool) $r['consent'], 'consentAt' => iso($r['consent_at']), 'consentSource' => $r['consent_source'], 'consentPage' => $r['consent_page'],
        'unsubscribedAt' => iso($r['unsubscribed_at']),
    ], $st->fetchAll());
    $counts = $pdo->query("SELECT COUNT(*) AS total, SUM(consent = 1 AND unsubscribed_at IS NULL) AS consent, SUM(unsubscribed_at IS NOT NULL) AS unsub FROM contacts")->fetch();
    respond(200, ['ok' => true, 'contacts' => $rows, 'counts' => array_map('intval', $counts)]);

case 'contacts_export':
    // CSV for Brevo / Mailchimp / Hostinger Reach. Default: only people who agreed to marketing.
    $a = current_agent($pdo);
    if (!$a['is_admin']) fail(403, 'Only admins can export contacts.');
    faj_leads_schema($pdo);
    $all = ($_GET['which'] ?? 'consent') === 'all';
    $rows = $pdo->query('SELECT * FROM contacts' . ($all ? '' : ' WHERE consent = 1 AND unsubscribed_at IS NULL') . ' ORDER BY first_seen')->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="fajtech-' . ($all ? 'all-contacts' : 'marketing-contacts') . '-' . gmdate('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // Excel-friendly UTF-8
    fputcsv($out, ['EMAIL', 'FIRSTNAME', 'LASTNAME', 'COMPANY', 'PHONE', 'SOURCES', 'FIRST_CONTACT_UTC', 'LAST_CONTACT_UTC', 'MARKETING_CONSENT', 'CONSENT_DATE_UTC', 'CONSENT_FORM', 'CONSENT_PAGE', 'CONSENT_IP', 'CONSENT_WORDING', 'UNSUBSCRIBED_UTC', 'UNSUBSCRIBE_LINK']);
    foreach ($rows as $r) {
        $parts = preg_split('/\s+/', trim((string) $r['name']), 2);
        fputcsv($out, [
            $r['email'], $parts[0] ?? '', $parts[1] ?? '', $r['company'], $r['phone'], $r['sources'], $r['first_seen'], $r['last_seen'],
            $r['consent'] && !$r['unsubscribed_at'] ? 'yes' : 'no', $r['consent_at'], $r['consent_source'], $r['consent_page'], $r['consent_ip'], $r['consent_text'],
            $r['unsubscribed_at'], "{$siteUrl}/unsubscribe/?t={$r['unsub_token']}",
        ]);
    }
    fclose($out);
    exit;

case 'contact_unsubscribe':
    require_post($method);
    $a = current_agent($pdo);
    if (!$a['is_admin']) fail(403, 'Only admins can change contacts.');
    $st = $pdo->prepare('SELECT unsub_token FROM contacts WHERE id = ?');
    $st->execute([(int) ($in['id'] ?? 0)]);
    $tok = (string) $st->fetchColumn();
    if ($tok === '' || faj_lead_unsubscribe($pdo, $tok, $ip, 'Unsubscribed by ' . $a['name'] . ' (team inbox)') === null) fail(404, 'Contact not found.');
    respond(200, ['ok' => true]);

case 'contact_delete':
    require_post($method);
    $a = current_agent($pdo);
    if (!$a['is_admin']) fail(403, 'Only admins can change contacts.');
    $pdo->prepare('DELETE FROM contacts WHERE id = ?')->execute([(int) ($in['id'] ?? 0)]);
    respond(200, ['ok' => true]);

case 'tg_me':
    $a = current_agent($pdo);
    $st = $pdo->prepare('SELECT tg_user_id FROM chat_agents WHERE id = ?'); $st->execute([$a['id']]);
    respond(200, ['ok' => true, 'connected' => (bool) (setting_get($pdo, 'tg_token') && setting_get($pdo, 'tg_chat_id')),
        'bot' => setting_get($pdo, 'tg_bot'), 'chat' => setting_get($pdo, 'tg_chat_title'), 'linked' => (bool) $st->fetchColumn()]);

case 'tg_link_code':
    require_post($method);
    $a = current_agent($pdo);
    if (!setting_get($pdo, 'tg_token')) fail(422, 'Telegram is not connected yet.');
    ensure_tg_webhook($pdo);
    $code = '';
    for ($i = 0; $i < 6; $i++) $code .= 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'[random_int(0, 31)];
    setting_set($pdo, "tg_link_{$code}", json_encode(['agent' => (int) $a['id'], 'exp' => time() + 900]));
    respond(200, ['ok' => true, 'code' => $code, 'bot' => setting_get($pdo, 'tg_bot'), 'chat' => setting_get($pdo, 'tg_chat_title')]);

case 'tg_unlink':
    require_post($method);
    $a = current_agent($pdo);
    $pdo->prepare('UPDATE chat_agents SET tg_user_id = NULL WHERE id = ?')->execute([$a['id']]);
    respond(200, ['ok' => true]);

case 'tg_test':
    require_post($method);
    $a = current_agent($pdo);
    if (!$a['is_admin']) fail(403, 'Only admins can change Telegram alerts.');
    if (!tg_send($pdo, '🔔 Test alert from the FAJTech website chat.', 'Open the inbox', 'https://fajtech.ca/agent/')) fail(422, 'Telegram did not accept the message. Is the bot still in the group?');
    respond(200, ['ok' => true]);

case 'tg_disconnect':
    require_post($method);
    $a = current_agent($pdo);
    if (!$a['is_admin']) fail(403, 'Only admins can change Telegram alerts.');
    tg_drop_webhook($pdo);
    foreach (['tg_token', 'tg_bot', 'tg_chat_id', 'tg_chat_title'] as $k) setting_set($pdo, $k, null);
    $pdo->exec('UPDATE chat_agents SET tg_user_id = NULL');
    respond(200, ['ok' => true]);
}

fail(400, 'Unknown action.');
