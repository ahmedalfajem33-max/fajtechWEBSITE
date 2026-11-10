<?php
/**
 * FAJTech contact form handler.
 * 1) Emails the enquiry to the team.
 * 2) Optionally creates a Lead in Zoho CRM (Web-to-Lead), including the Google Ads click ID.
 *
 * Settings live in fajtech-config.php OUTSIDE public_html (never in this repository).
 * See server/fajtech-config.example.php and README.md.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

function respond(int $code, array $body): void { http_response_code($code); echo json_encode($body); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') respond(405, ['ok' => false, 'error' => 'Method not allowed.']);

// Look for the config two levels up (…/domains/fajtech.ca/fajtech-config.php), then one level up.
$config = [];
foreach ([dirname(__DIR__, 2) . '/fajtech-config.php', dirname(__DIR__, 3) . '/fajtech-config.php'] as $path) {
    if (is_readable($path)) { $config = require $path; break; }
}
$to   = $config['mail_to']   ?? 'info@fajtech.ca';
$from = $config['mail_from'] ?? 'website@fajtech.ca';

// Spam trap.
if (!empty($_POST['website'])) respond(200, ['ok' => true]);

// Basic rate limit: 5 submissions per IP per 10 minutes.
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rlFile = sys_get_temp_dir() . '/faj_rl_' . md5($ip);
$hits = array_filter(json_decode((string) @file_get_contents($rlFile), true) ?: [], fn($t) => $t > time() - 600);
if (count($hits) >= 5) respond(429, ['ok' => false, 'error' => 'Too many messages, please wait a few minutes.']);
$hits[] = time();
@file_put_contents($rlFile, json_encode(array_values($hits)));

function field(string $k, int $max = 200): string {
    $v = trim((string) ($_POST[$k] ?? ''));
    $v = str_replace(["\r", "\0"], '', $v);
    return mb_substr($v, 0, $max);
}
$d = [
    'first_name' => field('first_name', 80),
    'last_name'  => field('last_name', 80),
    'email'      => field('email', 150),
    'phone'      => field('phone', 40),
    'company'    => field('company', 150),
    'employees'  => field('employees', 20),
    'interest'   => field('interest', 100),
    'message'    => field('message', 5000),
    'gclid'      => field('gclid', 200),
    'page'       => field('page', 300),
    'consent'    => field('marketing_consent', 5) === 'yes' ? 'Yes' : 'No',
];
foreach (['first_name', 'last_name', 'email', 'message'] as $req) {
    if ($d[$req] === '') respond(422, ['ok' => false, 'error' => 'Please fill in all required fields.']);
}
if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) respond(422, ['ok' => false, 'error' => 'Please enter a valid email address.']);
$oneLine = fn(string $s) => preg_replace('/[\n\t]+/', ' ', $s);

// ── 1) Email ─────────────────────────────────────────────
$name = $oneLine($d['first_name'] . ' ' . $d['last_name']);
$subject = 'Website enquiry: ' . $name . ($d['company'] ? ' (' . $oneLine($d['company']) . ')' : '');
$body = "New enquiry from fajtech.ca\n\n"
    . "Name:        {$name}\nEmail:       {$d['email']}\nPhone:       {$d['phone']}\n"
    . "Company:     {$d['company']}\nEmployees:   {$d['employees']}\nInterest:    {$d['interest']}\n"
    . "Marketing consent (CASL): {$d['consent']}\n\nMessage:\n{$d['message']}\n\n"
    . "Page: {$d['page']}\nGoogle Ads click ID: {$d['gclid']}\nIP: {$ip}\nTime: " . date('c') . "\n";
$headers = "From: FAJTech Website <{$from}>\r\nReply-To: {$d['email']}\r\nContent-Type: text/plain; charset=UTF-8";
$mailed = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers, '-f' . $from);

// ── 2) Zoho CRM Web-to-Lead (optional) ───────────────────
$zohoOk = null;
$z = $config['zoho_web_to_lead'] ?? null;
if (is_array($z) && !empty($z['xnQsjsdp']) && !empty($z['xmIwtLD'])) {
    $f = $z['fields'] ?? [];
    $post = [
        'xnQsjsdp'   => $z['xnQsjsdp'],
        'xmIwtLD'    => $z['xmIwtLD'],
        'actionType' => $z['actionType'] ?? 'TGVhZHM=',
        'returnURL'  => 'https://fajtech.ca/thank-you/',
        $f['first_name'] ?? 'First Name'  => $d['first_name'],
        $f['last_name']  ?? 'Last Name'   => $d['last_name'],
        $f['email']      ?? 'Email'       => $d['email'],
        $f['phone']      ?? 'Phone'       => $d['phone'],
        $f['company']    ?? 'Company'     => $d['company'] ?: $name,
        $f['description'] ?? 'Description' => "Interest: {$d['interest']}\nEmployees: {$d['employees']}\nMarketing consent: {$d['consent']}\n\n{$d['message']}",
        $f['lead_source'] ?? 'Lead Source' => $d['gclid'] ? 'Google AdWords' : 'Website',
    ];
    if (!empty($f['gclid'])) $post[$f['gclid']] = $d['gclid'];
    $ch = curl_init($z['url'] ?? 'https://crm.zoho.ca/crm/WebToLeadForm');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $zohoOk = $status >= 200 && $status < 400;
    if (!$zohoOk) error_log("FAJTech: Zoho Web-to-Lead returned HTTP {$status}");
}

if (!$mailed && $zohoOk !== true) {
    error_log('FAJTech: contact form could not send email or reach Zoho');
    respond(500, ['ok' => false, 'error' => 'Sorry, your message could not be sent.']);
}
respond(200, ['ok' => true]);
