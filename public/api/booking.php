<?php
/**
 * FAJTech consultation booking API (free alternative to Zoho Bookings).
 *
 *   GET  /api/booking.php?action=slots   -> available 30-min slots (UTC) for the next N days
 *   POST /api/booking.php  action=book   -> books a slot: creates the meeting in Exchange and
 *                                           sends invitations to the customer and the consultant
 *
 * Availability = working hours (business time zone) minus busy times read from the
 * consultant's Exchange calendar via Exchange Web Services (EWS).
 * The meeting is organised by the booking mailbox (e.g. booking@fajtech.ca), so no extra
 * Exchange permissions are needed beyond reading free/busy.
 *
 * All secrets live in fajtech-config.php OUTSIDE public_html. See server/fajtech-config.example.php.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

function respond(int $code, array $body): void { http_response_code($code); echo json_encode($body); exit; }

$config = [];
foreach ([dirname(__DIR__, 2) . '/fajtech-config.php', dirname(__DIR__, 3) . '/fajtech-config.php'] as $path) {
    if (is_readable($path)) { $config = require $path; break; }
}
$b = $config['booking'] ?? null;
if (!is_array($b) || empty($b['ews_url']) || (empty($b['ews_pass']) && empty($b['mock']))) {
    respond(503, ['ok' => false, 'error' => 'Online booking is not available right now.', 'fallback' => true]);
}

$tzBiz    = new DateTimeZone($b['timezone'] ?? 'America/Edmonton');
$utc      = new DateTimeZone('UTC');
$days     = $b['days'] ?? [1, 2, 3, 4, 5];            // ISO weekday numbers, 1 = Monday
$open     = $b['open'] ?? '09:00';
$close    = $b['close'] ?? '17:00';
$len      = (int) ($b['duration'] ?? 30);              // minutes
$buffer   = (int) ($b['buffer'] ?? 15);                // minutes kept free around existing meetings
$notice   = (int) ($b['min_notice_hours'] ?? 4);
$horizon  = (int) ($b['horizon_days'] ?? 30);
$owner    = $b['owner'] ?? 'ahmed.alfajem@fajtech.ca'; // whose calendar is checked & invited
$ownerName = $b['owner_name'] ?? 'Ahmed Alfajem';

// ── EWS helpers ────────────────────────────────────────────────────────────
function ews(array $b, string $body): DOMXPath {
    $xml = '<?xml version="1.0" encoding="utf-8"?>'
        . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:t="http://schemas.microsoft.com/exchange/services/2006/types" xmlns:m="http://schemas.microsoft.com/exchange/services/2006/messages">'
        . '<soap:Header><t:RequestServerVersion Version="' . ($b['ews_version'] ?? 'Exchange2013') . '"/></soap:Header>'
        . '<soap:Body>' . $body . '</soap:Body></soap:Envelope>';
    $ch = curl_init($b['ews_url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8'],
        CURLOPT_HTTPAUTH => CURLAUTH_NTLM | CURLAUTH_BASIC,
        CURLOPT_USERPWD => $b['ews_user'] . ':' . $b['ews_pass'],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $res = (string) curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($code !== 200 || $res === '') {
        error_log("FAJTech booking: EWS HTTP {$code} {$err} " . substr($res, 0, 300));
        throw new RuntimeException('calendar-unavailable');
    }
    $doc = new DOMDocument();
    if (!@$doc->loadXML($res)) throw new RuntimeException('calendar-bad-response');
    $xp = new DOMXPath($doc);
    $xp->registerNamespace('t', 'http://schemas.microsoft.com/exchange/services/2006/types');
    $xp->registerNamespace('m', 'http://schemas.microsoft.com/exchange/services/2006/messages');
    $fail = $xp->query('//m:ResponseMessage[@ResponseClass="Error"]/m:MessageText|//*[local-name()="FreeBusyResponse"]/*[local-name()="ResponseMessage"][@ResponseClass="Error"]/*[local-name()="MessageText"]');
    if ($fail && $fail->length) {
        error_log('FAJTech booking: EWS error: ' . $fail->item(0)->textContent);
        throw new RuntimeException('calendar-error');
    }
    return $xp;
}

/** Busy intervals [startTs, endTs] for the owner between two timestamps. */
function busyTimes(array $b, string $owner, int $from, int $to): array {
    if (!empty($b['mock'])) { // local testing without Exchange
        $d = (new DateTimeImmutable('@' . $from))->setTimezone(new DateTimeZone('America/Edmonton'))->modify('+1 day')->setTime(10, 0);
        return [[$d->getTimestamp(), $d->getTimestamp() + 3600]];
    }
    $utcZone = '<t:TimeZone><t:Bias>0</t:Bias><t:StandardTime><t:Bias>0</t:Bias><t:Time>00:00:00</t:Time><t:DayOrder>0</t:DayOrder><t:Month>0</t:Month><t:DayOfWeek>Sunday</t:DayOfWeek></t:StandardTime><t:DaylightTime><t:Bias>0</t:Bias><t:Time>00:00:00</t:Time><t:DayOrder>0</t:DayOrder><t:Month>0</t:Month><t:DayOfWeek>Sunday</t:DayOfWeek></t:DaylightTime></t:TimeZone>';
    $body = '<m:GetUserAvailabilityRequest>' . $utcZone
        . '<m:MailboxDataArray><t:MailboxData><t:Email><t:Address>' . htmlspecialchars($owner, ENT_XML1) . '</t:Address></t:Email>'
        . '<t:AttendeeType>Required</t:AttendeeType><t:ExcludeConflicts>false</t:ExcludeConflicts></t:MailboxData></m:MailboxDataArray>'
        . '<t:FreeBusyViewOptions><t:TimeWindow><t:StartTime>' . gmdate('Y-m-d\TH:i:s', $from) . '</t:StartTime><t:EndTime>' . gmdate('Y-m-d\TH:i:s', $to) . '</t:EndTime></t:TimeWindow>'
        . '<t:MergedFreeBusyIntervalInMinutes>15</t:MergedFreeBusyIntervalInMinutes><t:RequestedView>FreeBusy</t:RequestedView></t:FreeBusyViewOptions>'
        . '</m:GetUserAvailabilityRequest>';
    $xp = ews($b, $body);
    $out = [];
    foreach ($xp->query('//t:CalendarEvent') as $ev) {
        $type = $xp->evaluate('string(t:BusyType)', $ev);
        if ($type === 'Free') continue;
        $s = strtotime($xp->evaluate('string(t:StartTime)', $ev) . 'Z');
        $e = strtotime($xp->evaluate('string(t:EndTime)', $ev) . 'Z');
        if ($s && $e) $out[] = [$s, $e];
    }
    return $out;
}

/** All free slot start timestamps within the booking horizon. */
function freeSlots(array $b, DateTimeZone $tzBiz, array $days, string $open, string $close, int $len, int $buffer, int $notice, int $horizon, string $owner): array {
    $now = time();
    $earliest = $now + $notice * 3600;
    $startDay = (new DateTimeImmutable('today', $tzBiz));
    $endTs = $startDay->modify("+{$horizon} days")->getTimestamp();

    $cacheFile = sys_get_temp_dir() . '/faj_busy_' . md5($owner . $b['ews_url']);
    $busy = null;
    if (is_readable($cacheFile) && filemtime($cacheFile) > $now - 120) $busy = json_decode((string) file_get_contents($cacheFile), true);
    if (!is_array($busy)) {
        $busy = busyTimes($b, $owner, $startDay->getTimestamp(), $endTs);
        @file_put_contents($cacheFile, json_encode($busy));
    }

    $slots = [];
    for ($d = $startDay; $d->getTimestamp() < $endTs; $d = $d->modify('+1 day')) {
        if (!in_array((int) $d->format('N'), $days, true)) continue;
        [$oh, $om] = array_map('intval', explode(':', $open));
        [$ch, $cm] = array_map('intval', explode(':', $close));
        $t = $d->setTime($oh, $om)->getTimestamp();
        $last = $d->setTime($ch, $cm)->getTimestamp() - $len * 60;
        for (; $t <= $last; $t += $len * 60) {
            if ($t < $earliest) continue;
            $s = $t - $buffer * 60; $e = $t + ($len + $buffer) * 60;
            $clash = false;
            foreach ($busy as [$bs, $be]) { if ($bs < $e && $be > $s) { $clash = true; break; } }
            if (!$clash) $slots[] = $t;
        }
    }
    return $slots;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── GET slots ──────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'slots') {
    try {
        $slots = freeSlots($b, $tzBiz, $days, $open, $close, $len, $buffer, $notice, $horizon, $owner);
    } catch (Throwable $e) {
        respond(503, ['ok' => false, 'error' => 'Our calendar is temporarily unavailable.', 'fallback' => true]);
    }
    respond(200, [
        'ok' => true,
        'duration' => $len,
        'businessTimeZone' => $tzBiz->getName(),
        'consultant' => $ownerName,
        'slots' => array_map(fn($t) => gmdate('Y-m-d\TH:i:s\Z', $t), $slots),
    ]);
}

if ($method !== 'POST' || $action !== 'book') respond(405, ['ok' => false, 'error' => 'Method not allowed.']);

// ── POST book ──────────────────────────────────────────────────────────────
if (!empty($_POST['website'])) respond(200, ['ok' => true]); // spam trap

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rlFile = sys_get_temp_dir() . '/faj_bk_' . md5($ip);
$hits = array_filter(json_decode((string) @file_get_contents($rlFile), true) ?: [], fn($t) => $t > time() - 3600);
if (count($hits) >= 3) respond(429, ['ok' => false, 'error' => 'Too many bookings from your connection. Please call or WhatsApp us.']);

function f(string $k, int $max = 200): string {
    $v = trim(str_replace(["\r", "\0"], '', (string) ($_POST[$k] ?? '')));
    return mb_substr($v, 0, $max);
}
$d = [
    'first_name' => f('first_name', 60), 'last_name' => f('last_name', 60), 'email' => f('email', 150),
    'phone' => f('phone', 40), 'company' => f('company', 120), 'notes' => f('notes', 1500),
    'slot' => f('slot', 25), 'tz' => f('tz', 60), 'gclid' => f('gclid', 200),
    'consent' => f('marketing_consent', 5) === 'yes' ? 'Yes' : 'No',
];
foreach (['first_name', 'last_name', 'email', 'slot'] as $req) if ($d[$req] === '') respond(422, ['ok' => false, 'error' => 'Please fill in all required fields.']);
if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) respond(422, ['ok' => false, 'error' => 'Please enter a valid email address.']);
if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:00Z$/', $d['slot'])) respond(422, ['ok' => false, 'error' => 'Please choose a time.']);
$start = strtotime($d['slot']);

try {
    @unlink(sys_get_temp_dir() . '/faj_busy_' . md5($owner . $b['ews_url'])); // re-check live availability
    $free = freeSlots($b, $tzBiz, $days, $open, $close, $len, $buffer, $notice, $horizon, $owner);
} catch (Throwable $e) {
    respond(503, ['ok' => false, 'error' => 'Our calendar is temporarily unavailable. Please call or WhatsApp us.', 'fallback' => true]);
}
if (!in_array($start, $free, true)) respond(409, ['ok' => false, 'error' => 'Sorry, that time was just taken. Please choose another time.', 'refresh' => true]);

$end = $start + $len * 60;
$name = trim($d['first_name'] . ' ' . $d['last_name']);
$x = fn(string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
$h = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$link = $b['meeting_link'] ?? '';
$custTz = in_array($d['tz'], DateTimeZone::listIdentifiers(), true) ? $d['tz'] : $tzBiz->getName();
$whenCust = (new DateTimeImmutable('@' . $start))->setTimezone(new DateTimeZone($custTz))->format('l, F j, Y \a\t g:i A') . ' (' . str_replace('_', ' ', $custTz) . ')';
$whenBiz  = (new DateTimeImmutable('@' . $start))->setTimezone($tzBiz)->format('l, F j, Y \a\t g:i A T');

$joinHtml = $link
    ? '<p><strong>Join the meeting:</strong> <a href="' . $h($link) . '">' . $h($link) . '</a></p>'
    : '<p>We will send you the meeting link before the call. If you prefer a phone call, just reply with your number.</p>';
$bodyHtml = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#1e293b">'
    . '<p>Hi ' . $h($d['first_name']) . ',</p>'
    . '<p>Thank you for booking a free 30-minute IT consultation with FAJTech. We look forward to speaking with you.</p>'
    . '<p><strong>When:</strong> ' . $h($whenCust) . '<br><strong>With:</strong> ' . $h($ownerName) . ', FAJTech</p>'
    . $joinHtml
    . '<p>Need to reschedule? Reply to this email, call +1 (403) 926-4494 or WhatsApp +20 122 370 4138 (24/7).</p>'
    . '<hr style="border:none;border-top:1px solid #e2e8f0"><p style="color:#64748b;font-size:12px">'
    . 'Booking details — Company: ' . $h($d['company'] ?: '—') . ' · Phone: ' . $h($d['phone'] ?: '—')
    . ($d['notes'] !== '' ? '<br>Notes: ' . nl2br($h($d['notes'])) : '')
    . '<br>FAJTech · Managed IT &amp; Cybersecurity · 24/7/365 support · <a href="https://fajtech.ca">fajtech.ca</a></p></div>';

$subject = 'FAJTech IT Consultation — ' . ($d['company'] !== '' ? $d['company'] : $name);
$create = '<m:CreateItem SendMeetingInvitations="SendToAllAndSaveCopy"><m:Items><t:CalendarItem>'
    . '<t:Subject>' . $x($subject) . '</t:Subject>'
    . '<t:Body BodyType="HTML">' . $x($bodyHtml) . '</t:Body>'
    . '<t:ReminderIsSet>true</t:ReminderIsSet><t:ReminderMinutesBeforeStart>15</t:ReminderMinutesBeforeStart>'
    . '<t:Start>' . gmdate('Y-m-d\TH:i:s\Z', $start) . '</t:Start><t:End>' . gmdate('Y-m-d\TH:i:s\Z', $end) . '</t:End>'
    . '<t:Location>' . $x($link ? 'Microsoft Teams — ' . $link : 'Online / phone (link to follow)') . '</t:Location>'
    . '<t:RequiredAttendees>'
    . '<t:Attendee><t:Mailbox><t:Name>' . $x($name) . '</t:Name><t:EmailAddress>' . $x($d['email']) . '</t:EmailAddress></t:Mailbox></t:Attendee>'
    . '<t:Attendee><t:Mailbox><t:Name>' . $x($ownerName) . '</t:Name><t:EmailAddress>' . $x($owner) . '</t:EmailAddress></t:Mailbox></t:Attendee>'
    . '</t:RequiredAttendees>'
    . '</t:CalendarItem></m:Items></m:CreateItem>';

if (empty($b['mock'])) {
    try { ews($b, $create); }
    catch (Throwable $e) { respond(503, ['ok' => false, 'error' => 'We could not complete the booking. Please call or WhatsApp us.', 'fallback' => true]); }
}
$hits[] = time();
@file_put_contents($rlFile, json_encode(array_values($hits)));
@unlink(sys_get_temp_dir() . '/faj_busy_' . md5($owner . $b['ews_url']));

// Team notification (lead record), like the website forms.
$to = $config['mail_to'] ?? 'info@fajtech.ca';
$from = $config['mail_from'] ?? 'website@fajtech.ca';
$txt = "NEW CONSULTATION BOOKED on fajtech.ca (invite sent from the booking mailbox)\n\n"
    . "When: {$whenBiz}\nCustomer's time: {$whenCust}\n\nName: {$name}\nEmail: {$d['email']}\nPhone: {$d['phone']}\nCompany: {$d['company']}\n"
    . "Marketing consent (CASL): {$d['consent']}\nGoogle Ads click ID: {$d['gclid']}\n\nNotes:\n{$d['notes']}\n";
@mail($to, '=?UTF-8?B?' . base64_encode('Consultation booked: ' . $name . ' — ' . $whenBiz) . '?=', $txt,
    "From: FAJTech Website <{$from}>\r\nReply-To: {$d['email']}\r\nContent-Type: text/plain; charset=UTF-8", '-f' . $from);

respond(200, ['ok' => true, 'when' => $whenCust]);
