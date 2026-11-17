<?php
/**
 * FAJTech website mailer.
 * fajtech.ca mail is hosted on our own Exchange server, which refuses mail that comes from the
 * internet claiming to be from @fajtech.ca. So we send through Exchange itself (EWS, over HTTPS)
 * with the booking mailbox from fajtech-config.php, and fall back to PHP mail() if that fails.
 * Loaded by other scripts only.
 */
declare(strict_types=1);
if (!defined('FAJMAILER')) { http_response_code(404); exit; }

/** @return bool true when Exchange accepted the message */
function faj_ews_send(array $b, string $to, string $subject, string $body, string $replyTo = ''): bool {
    $x = fn(string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $item = '<t:Message>'
        . '<t:Subject>' . $x($subject) . '</t:Subject>'
        . '<t:Body BodyType="Text">' . $x($body) . '</t:Body>'
        . '<t:ToRecipients><t:Mailbox><t:EmailAddress>' . $x($to) . '</t:EmailAddress></t:Mailbox></t:ToRecipients>'
        . ($replyTo !== '' ? '<t:ReplyTo><t:Mailbox><t:EmailAddress>' . $x($replyTo) . '</t:EmailAddress></t:Mailbox></t:ReplyTo>' : '')
        . '</t:Message>';
    $xml = '<?xml version="1.0" encoding="utf-8"?>'
        . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:t="http://schemas.microsoft.com/exchange/services/2006/types" xmlns:m="http://schemas.microsoft.com/exchange/services/2006/messages">'
        . '<soap:Header><t:RequestServerVersion Version="' . $x($b['ews_version'] ?? 'Exchange2013') . '"/></soap:Header>'
        . '<soap:Body><m:CreateItem MessageDisposition="SendOnly"><m:Items>' . $item . '</m:Items></m:CreateItem></soap:Body></soap:Envelope>';
    $ch = curl_init($b['ews_url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $xml, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8'],
        CURLOPT_HTTPAUTH => CURLAUTH_NTLM | CURLAUTH_BASIC,
        CURLOPT_USERPWD => $b['ews_user'] . ':' . $b['ews_pass'],
        CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $res = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ok = $code === 200 && str_contains($res, 'ResponseClass="Success"');
    if (!$ok) error_log("FAJTech mailer: EWS send failed (HTTP {$code}) " . substr(strip_tags($res), 0, 300));
    return $ok;
}

/** Send a plain-text email: through Exchange when configured, otherwise (or on failure) with PHP mail(). */
function faj_send_mail(array $config, string $to, string $subject, string $body, string $replyTo = ''): bool {
    $b = (array) ($config['booking'] ?? []);
    $useEws = ($config['mail_via'] ?? 'exchange') !== 'php'
        && !empty($b['ews_url']) && !empty($b['ews_user'])
        && !empty($b['ews_pass']) && $b['ews_pass'] !== 'PASTE_PASSWORD_HERE' && empty($b['mock']);
    if ($useEws && faj_ews_send($b, $to, $subject, $body, $replyTo)) return true;
    $from = $config['mail_from'] ?? 'website@fajtech.ca';
    $h = "From: FAJTech <{$from}>\r\n" . ($replyTo ? "Reply-To: {$replyTo}\r\n" : '') . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8";
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $h, '-f' . $from);
}
