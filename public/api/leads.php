<?php
/**
 * FAJTech contacts & marketing consent (CASL).
 * Every person who uses the contact, assessment or booking forms or the live chat is saved as a
 * contact. People who tick the marketing box get a consent record (when, which form/page, IP and
 * the exact wording) so we can prove express consent under CASL. Each contact has a private
 * unsubscribe link (fajtech.ca/unsubscribe/?t=...).
 * Uses the chat database (login in fajtech-chat.php outside public_html). Loaded by other scripts only.
 */
declare(strict_types=1);
if (!defined('FAJLEADS')) { http_response_code(404); exit; }

/** The exact checkbox wording shown on the website forms (stored with each consent). */
const FAJ_CONSENT_TEXT = 'Yes, send me occasional IT tips and news from FAJTech. You can unsubscribe at any time.';

function faj_leads_schema(PDO $pdo): void {
    $flag = sys_get_temp_dir() . '/faj_leads_schema_v1_' . md5((string) $pdo->query('SELECT DATABASE()')->fetchColumn());
    if (is_file($flag)) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS contacts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(150) NOT NULL UNIQUE,
        name VARCHAR(160) NOT NULL DEFAULT '',
        company VARCHAR(150) NOT NULL DEFAULT '',
        phone VARCHAR(40) NOT NULL DEFAULT '',
        sources VARCHAR(100) NOT NULL DEFAULT '',
        first_source VARCHAR(20) NOT NULL DEFAULT '',
        interactions INT UNSIGNED NOT NULL DEFAULT 1,
        first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        consent TINYINT(1) NOT NULL DEFAULT 0,
        consent_at DATETIME NULL,
        consent_source VARCHAR(20) NULL,
        consent_page VARCHAR(300) NULL,
        consent_ip VARCHAR(45) NULL,
        consent_text VARCHAR(300) NULL,
        unsubscribed_at DATETIME NULL,
        unsub_token CHAR(32) NOT NULL UNIQUE,
        KEY consent (consent, unsubscribed_at),
        KEY last_seen (last_seen)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_consent_log (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        contact_id INT UNSIGNED NOT NULL,
        action ENUM('consent','unsubscribe') NOT NULL,
        source VARCHAR(20) NOT NULL DEFAULT '',
        page VARCHAR(300) NOT NULL DEFAULT '',
        ip VARCHAR(45) NOT NULL DEFAULT '',
        detail VARCHAR(300) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY contact_id (contact_id),
        CONSTRAINT fk_consent_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // One-time import of people who already used the chat.
    $hasChat = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chat_threads'")->fetchColumn();
    if ($hasChat) {
        foreach ($pdo->query('SELECT email, name, MIN(created_at) AS first_seen, MAX(last_message_at) AS last_seen, COUNT(*) AS n FROM chat_threads GROUP BY email, name') as $r) {
            $email = mb_strtolower(trim((string) $r['email']));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            $pdo->prepare("INSERT IGNORE INTO contacts (email, name, sources, first_source, interactions, first_seen, last_seen, unsub_token) VALUES (?,?,'chat','chat',?,?,?,?)")
                ->execute([$email, (string) $r['name'], (int) $r['n'], $r['first_seen'], $r['last_seen'], bin2hex(random_bytes(16))]);
        }
    }
    @touch($flag);
}

/** Connect to the chat/contacts database (for scripts that don't have a connection yet). */
function faj_leads_db(): ?PDO {
    foreach ([dirname(__DIR__, 2), dirname(__DIR__, 3)] as $dir) {
        if (!is_readable("$dir/fajtech-chat.php")) continue;
        $c = (array) require "$dir/fajtech-chat.php";
        if (empty($c['db_name'])) return null;
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['db_host'] ?? '127.0.0.1', (int) ($c['db_port'] ?? 3306), $c['db_name']),
                $c['db_user'], $c['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
            );
            $pdo->exec("SET time_zone = '+00:00'");
            return $pdo;
        } catch (Throwable $e) {
            error_log('FAJTech leads DB: ' . $e->getMessage());
            return null;
        }
    }
    return null;
}

/**
 * Save or update a contact.
 * $d: email, name, company, phone, source ('contact'|'assessment'|'booking'|'chat'), page, ip, consent (bool)
 */
function faj_lead_save(PDO $pdo, array $d): void {
    faj_leads_schema($pdo);
    $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return;
    $source = preg_replace('/[^a-z]/', '', (string) ($d['source'] ?? 'contact')) ?: 'contact';
    $name = mb_substr(trim((string) ($d['name'] ?? '')), 0, 160);
    $company = mb_substr(trim((string) ($d['company'] ?? '')), 0, 150);
    $phone = mb_substr(trim((string) ($d['phone'] ?? '')), 0, 40);
    $page = mb_substr((string) ($d['page'] ?? ''), 0, 300);
    $ip = mb_substr((string) ($d['ip'] ?? ''), 0, 45);

    $pdo->prepare("INSERT INTO contacts (email, name, company, phone, sources, first_source, first_seen, last_seen, unsub_token)
            VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?)
        ON DUPLICATE KEY UPDATE
            name = IF(VALUES(name) <> '', VALUES(name), name),
            company = IF(VALUES(company) <> '', VALUES(company), company),
            phone = IF(VALUES(phone) <> '', VALUES(phone), phone),
            sources = IF(FIND_IN_SET(VALUES(first_source), sources), sources, TRIM(BOTH ',' FROM CONCAT(sources, ',', VALUES(first_source)))),
            interactions = interactions + 1,
            last_seen = UTC_TIMESTAMP()")
        ->execute([$email, $name, $company, $phone, $source, $source, bin2hex(random_bytes(16))]);

    if (!empty($d['consent'])) {
        // New express consent (a ticked box) also overrides an earlier unsubscribe.
        $pdo->prepare('UPDATE contacts SET consent = 1, consent_at = UTC_TIMESTAMP(), consent_source = ?, consent_page = ?, consent_ip = ?, consent_text = ?, unsubscribed_at = NULL WHERE email = ?')
            ->execute([$source, $page, $ip, FAJ_CONSENT_TEXT, $email]);
        $pdo->prepare("INSERT INTO contact_consent_log (contact_id, action, source, page, ip, detail) SELECT id, 'consent', ?, ?, ?, ? FROM contacts WHERE email = ?")
            ->execute([$source, $page, $ip, FAJ_CONSENT_TEXT, $email]);
    }
}

/** Save a contact without ever breaking the form that called it. */
function faj_lead_save_safe(?PDO $pdo, array $d): void {
    try {
        $pdo ??= faj_leads_db();
        if ($pdo) faj_lead_save($pdo, $d);
    } catch (Throwable $e) {
        error_log('FAJTech leads: ' . $e->getMessage());
    }
}

/** Unsubscribe by private token. Returns the email, or null if the link is invalid. */
function faj_lead_unsubscribe(PDO $pdo, string $token, string $ip, string $detail = 'Unsubscribe link'): ?string {
    faj_leads_schema($pdo);
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
    $st = $pdo->prepare('SELECT id, email, unsubscribed_at FROM contacts WHERE unsub_token = ?');
    $st->execute([$token]);
    $c = $st->fetch();
    if (!$c) return null;
    if (!$c['unsubscribed_at']) {
        $pdo->prepare('UPDATE contacts SET unsubscribed_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$c['id']]);
        $pdo->prepare("INSERT INTO contact_consent_log (contact_id, action, ip, detail) VALUES (?, 'unsubscribe', ?, ?)")->execute([$c['id'], $ip, $detail]);
    }
    return (string) $c['email'];
}
