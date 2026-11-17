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
 *   login, logout, me, password, list, thread, reply, close, reopen, agents, agent_add, agent_remove
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

function respond(int $code, array $body): void { http_response_code($code); echo json_encode($body, JSON_UNESCAPED_UNICODE); exit; }
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
}
$schemaFlag = sys_get_temp_dir() . '/faj_chat_schema_v1_' . md5((string) $chatCfg['db_name']);
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
function send_mail(string $to, string $subject, string $body, string $from, string $replyTo = ''): void {
    $h = "From: FAJTech <{$from}>\r\n" . ($replyTo ? "Reply-To: {$replyTo}\r\n" : '') . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8";
    @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $h, '-f' . $from);
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

// ── Visitor actions ─────────────────────────────────────────────────────────
switch ($action) {
case 'status':
    respond(200, ['ok' => true, 'online' => agents_online($pdo)]);

case 'start':
    require_post($method);
    if (!empty($in['website'])) respond(200, ['ok' => true, 'token' => str_repeat('0', 64), 'messages' => []]); // spam trap
    if (!rate_ok("start:$ip", 4, 900)) fail(429, 'Too many new chats from your connection. Please wait a few minutes.');
    $name = str_in($in, 'name', 80);
    $email = str_in($in, 'email', 150);
    $text = str_in($in, 'message', 3000);
    $page = str_in($in, 'page', 300);
    if ($name === '' || $text === '') fail(422, 'Please enter your name and your question.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail(422, 'Please enter a valid email address so we can reply.');
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO chat_threads (token, name, email, page, ip, last_visitor_at, last_message_at, created_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())')
        ->execute([$token, $name, $email, $page, $ip]);
    $tid = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO chat_messages (thread_id, sender, body, created_at) VALUES (?, 'visitor', ?, UTC_TIMESTAMP())")->execute([$tid, $text]);
    send_mail($mailTo, "New website chat from {$name}",
        "New chat on fajtech.ca\n\nName: {$name}\nEmail: {$email}\nPage: {$page}\n\nMessage:\n{$text}\n\nReply in the team inbox: {$siteUrl}/agent/#chat-{$tid}\n",
        $mailFrom, $email);
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
    if ($wasClosed) {
        send_mail($mailTo, "Chat reopened by {$t['name']}", "{$t['name']} ({$t['email']}) wrote again:\n\n{$text}\n\nReply: {$siteUrl}/agent/#chat-{$t['id']}\n", $mailFrom, $t['email']);
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
    $st = $pdo->prepare('SELECT id, name, email, is_admin, must_change FROM chat_agents WHERE id = ?');
    $st->execute([$id]);
    $a = $st->fetch();
    if (!$a) { $_SESSION = []; fail(401, 'Please sign in.'); }
    $pdo->prepare('UPDATE chat_agents SET last_seen_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
    return $a;
}
function agent_out(array $a): array {
    return ['id' => (int) $a['id'], 'name' => $a['name'], 'email' => $a['email'], 'admin' => (bool) $a['is_admin'], 'mustChange' => (bool) $a['must_change']];
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
    $pdo->prepare("INSERT INTO chat_messages (thread_id, sender, agent_name, body, created_at) VALUES (?, 'agent', ?, ?, UTC_TIMESTAMP())")->execute([$id, $a['name'], $text]);
    $mid = (int) $pdo->lastInsertId();
    $pdo->prepare("UPDATE chat_threads SET status = 'open', last_message_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$id]);
    // Visitor has left the page? Send the reply by email, with a link back into the conversation.
    $away = strtotime($t['last_visitor_at'] . ' UTC') < time() - 120;
    if ($away) {
        $first = explode(' ', $t['name'])[0];
        send_mail($t['email'], 'FAJTech replied to your chat',
            "Hi {$first},\n\n{$a['name']} from FAJTech replied to your chat:\n\n{$text}\n\nContinue the conversation: {$siteUrl}/?chat={$t['token']}\nOr simply reply to this email.\n\nFAJTech · Managed IT & Cybersecurity\n",
            $mailFrom, $mailTo);
    }
    respond(200, ['ok' => true, 'id' => $mid, 'emailed' => $away]);

case 'close':
case 'reopen':
    require_post($method);
    current_agent($pdo);
    $pdo->prepare('UPDATE chat_threads SET status = ? WHERE id = ?')->execute([$action === 'close' ? 'closed' : 'open', (int) ($in['id'] ?? 0)]);
    respond(200, ['ok' => true]);

case 'agents':
    $a = current_agent($pdo);
    if (!$a['is_admin']) fail(403, 'Only admins can manage the team.');
    $rows = $pdo->query('SELECT id, name, email, is_admin, must_change, last_seen_at FROM chat_agents ORDER BY name')->fetchAll();
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
}

fail(400, 'Unknown action.');
