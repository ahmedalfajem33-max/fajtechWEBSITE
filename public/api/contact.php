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
    'form_type'  => in_array(field('form_type', 20), ['booking', 'assessment'], true) ? field('form_type', 20) : 'contact',
    'pref_date'  => field('preferred_date', 20),
    'pref_time'  => field('preferred_time', 40),
    'meeting'    => field('meeting_type', 40),
];
$isBooking = $d['form_type'] === 'booking';
if ($isBooking && ($d['pref_date'] === '' || $d['pref_time'] === '')) respond(422, ['ok' => false, 'error' => 'Please choose a preferred day and time.']);
$bookingText = $isBooking ? "Requested consultation: {$d['pref_date']} — {$d['pref_time']} (Mountain Time), {$d['meeting']}\n" : '';
foreach (['first_name', 'last_name', 'email', 'message'] as $req) {
    if ($d[$req] === '') respond(422, ['ok' => false, 'error' => 'Please fill in all required fields.']);
}
if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) respond(422, ['ok' => false, 'error' => 'Please enter a valid email address.']);
$oneLine = fn(string $s) => preg_replace('/[\n\t]+/', ' ', $s);

// ── 1) Email ─────────────────────────────────────────────
$name = $oneLine($d['first_name'] . ' ' . $d['last_name']);
$subject = ($isBooking ? 'Consultation request: ' : ($d['form_type'] === 'assessment' ? 'FREE ASSESSMENT request: ' : 'Website enquiry: ')) . $name . ($d['company'] ? ' (' . $oneLine($d['company']) . ')' : '');
$body = ($isBooking ? "New CONSULTATION REQUEST from fajtech.ca — please confirm the time with the client.\n\n" : "New enquiry from fajtech.ca\n\n")
    . $bookingText . "\n"
    . "Name:        {$name}\nEmail:       {$d['email']}\nPhone:       {$d['phone']}\n"
    . "Company:     {$d['company']}\nEmployees:   {$d['employees']}\nInterest:    {$d['interest']}\n"
    . "Marketing consent (CASL): {$d['consent']}\n\nMessage:\n{$d['message']}\n\n"
    . "Page: {$d['page']}\nGoogle Ads click ID: {$d['gclid']}\nIP: {$ip}\nTime: " . date('c') . "\n";
// Our team inbox is on our own Exchange server: send through it (see mailer.php).
define('FAJMAILER', true);
require_once __DIR__ . '/mailer.php';
$mailed = faj_send_mail(['mail_from' => $from] + $config, $to, $subject, $body, $d['email']);

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
        $f['description'] ?? 'Description' => $bookingText . "Interest: {$d['interest']}\nEmployees: {$d['employees']}\nMarketing consent: {$d['consent']}\n\n{$d['message']}",
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

// ── 3) Confirmation email to the customer, from info@fajtech.ca ─────────
// Anti-abuse: the email only repeats a cleaned first name and the chosen day/time,
// never the visitor's free-text message, so the form can't be used to send spam.
$replyFrom = $config['confirm_from'] ?? 'info@fajtech.ca';
$first = preg_split('/\s+/u', trim($d['first_name']))[0] ?? '';
$first = preg_replace("/[^\\p{L}\\p{M}'\\-]/u", '', $first);
$first = mb_substr($first, 0, 20) ?: 'there';
$h = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$allowedTimes = ['Morning (8–10 am)', 'Late morning (10 am–12 pm)', 'Early afternoon (12–2 pm)', 'Afternoon (2–4 pm)', 'Late afternoon (4–6 pm)'];
$allowedMeet  = ['Video call (Cisco Webex)', 'Phone call', 'In person (Calgary area)'];
$prefDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['pref_date']) ? date('l, F j, Y', strtotime($d['pref_date'])) : '';
$prefTime = in_array($d['pref_time'], $allowedTimes, true) ? $d['pref_time'] : '';
$meeting  = in_array($d['meeting'], $allowedMeet, true) ? $d['meeting'] : '';

if ($isBooking) {
    $cSubject = 'We received your consultation request — FAJTech';
    $lead = 'Thank you for requesting a free IT consultation with FAJTech. We have received your request and will confirm your appointment by email within one business day.';
    $details = array_filter(['Preferred day' => $prefDate, 'Preferred time (Mountain Time)' => $prefTime, 'Meeting type' => $meeting]);
} elseif ($d['form_type'] === 'assessment') {
    $cSubject = 'We received your free IT assessment request — FAJTech';
    $lead = 'Thank you for requesting a free IT & cybersecurity assessment. A member of our team will contact you within one business day to arrange it.';
    $details = [];
} else {
    $cSubject = 'Thanks for contacting FAJTech';
    $lead = 'Thank you for contacting FAJTech. We have received your message and will reply within one business day.';
    $details = [];
}

$rowsHtml = '';
$rowsText = '';
foreach ($details as $k => $v) {
    $rowsHtml .= '<tr><td style="padding:6px 12px 6px 0;color:#64748b">' . $h($k) . '</td><td style="padding:6px 0;font-weight:600;color:#111c2a">' . $h($v) . '</td></tr>';
    $rowsText .= "{$k}: {$v}\n";
}
$text = "Hi {$first},\n\n{$lead}\n\n" . ($rowsText ? $rowsText . "\n" : '')
    . "Need help sooner?\n- Call: +1 (403) 926-4494 (8 am – 7 pm Calgary time)\n- WhatsApp 24/7: +20 122 370 4138 (https://wa.me/201223704138)\n- Email: info@fajtech.ca\n\n"
    . "Kind regards,\nThe FAJTech team\nManaged IT & Cybersecurity — 24/7/365 support\nhttps://fajtech.ca\n415 Pinewind Rd NE, Calgary, AB T1Y 2M8\n\n"
    . "You received this email because this address was used on a form at fajtech.ca. If that wasn't you, please ignore this message.";
$html = '<!doctype html><html><body style="margin:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif">'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px"><tr><td align="center">'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden">'
    . '<tr><td style="background:#111c2a;padding:20px 28px;color:#ffffff;font-size:22px;font-weight:800">FAJ<span style="color:#30c9a2">Tech</span></td></tr>'
    . '<tr><td style="padding:28px;color:#334155;font-size:15px;line-height:1.6">'
    . '<p style="margin:0 0 14px">Hi ' . $h($first) . ',</p>'
    . '<p style="margin:0 0 18px">' . $h($lead) . '</p>'
    . ($rowsHtml ? '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px;font-size:15px">' . $rowsHtml . '</table>' : '')
    . '<p style="margin:0 0 8px;font-weight:700;color:#111c2a">Need help sooner?</p>'
    . '<p style="margin:0 0 4px">Call <a href="tel:+14039264494" style="color:#0f7a64">+1 (403) 926-4494</a> (8 am – 7 pm Calgary time)</p>'
    . '<p style="margin:0 0 4px">WhatsApp 24/7: <a href="https://wa.me/201223704138" style="color:#0f7a64">+20 122 370 4138</a></p>'
    . '<p style="margin:0 0 22px">Email: <a href="mailto:info@fajtech.ca" style="color:#0f7a64">info@fajtech.ca</a></p>'
    . '<p style="margin:0">Kind regards,<br><strong>The FAJTech team</strong></p>'
    . '</td></tr>'
    . '<tr><td style="background:#f8fafc;padding:16px 28px;color:#64748b;font-size:12px;line-height:1.5">Managed IT &amp; Cybersecurity · 24/7/365 support<br>415 Pinewind Rd NE, Calgary, AB T1Y 2M8 · <a href="https://fajtech.ca" style="color:#0f7a64">fajtech.ca</a><br>You received this email because this address was used on a form at fajtech.ca. If that wasn\'t you, please ignore this message.</td></tr>'
    . '</table></td></tr></table></body></html>';

// Sent through our Exchange server (see mailer.php) from the booking mailbox; replies go to info@.
// To show it as from info@fajtech.ca instead, pass $replyFrom as the last argument once the booking
// mailbox has Send As permission on info@ in Exchange.
if (!faj_send_mail($config, $d['email'], $cSubject, $text, $replyFrom, $html)) {
    error_log('FAJTech: confirmation email to customer could not be sent');
}

respond(200, ['ok' => true]);
