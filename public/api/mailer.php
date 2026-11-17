<?php
/**
 * FAJTech website mailer.
 * fajtech.ca mail is hosted on our own Exchange server, which refuses mail that comes from the
 * internet claiming to be from @fajtech.ca, and Hostinger's PHP mail() is not reliably delivered to
 * customers either (not signed for fajtech.ca). So all website email goes through Exchange itself
 * (EWS, over HTTPS) with the booking mailbox from fajtech-config.php, falling back to mail().
 * Loaded by other scripts only.
 */
declare(strict_types=1);
if (!defined('FAJMAILER')) { http_response_code(404); exit; }

/** @return bool true when Exchange accepted the message */
function faj_ews_send(array $b, string $to, string $subject, string $body, string $replyTo = '', bool $isHtml = false, string $fromAddr = ''): bool {
    $x = fn(string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $mbox = fn(string $tag, string $addr) => "<t:{$tag}><t:Mailbox><t:EmailAddress>" . $x($addr) . "</t:EmailAddress></t:Mailbox></t:{$tag}>";
    $item = '<t:Message>'
        . '<t:Subject>' . $x($subject) . '</t:Subject>'
        . '<t:Body BodyType="' . ($isHtml ? 'HTML' : 'Text') . '">' . $x($body) . '</t:Body>'
        . '<t:ToRecipients><t:Mailbox><t:EmailAddress>' . $x($to) . '</t:EmailAddress></t:Mailbox></t:ToRecipients>'
        . ($fromAddr !== '' ? $mbox('From', $fromAddr) : '')
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

/**
 * Send an email through our Exchange server (EWS) with the booking mailbox, so it is delivered and
 * trusted like any other fajtech.ca email. Falls back to PHP mail() (Hostinger) if Exchange fails.
 * $fromAddr: show this address as the sender (e.g. info@fajtech.ca); if the booking mailbox isn't
 *            allowed to send as it, the email is sent from the booking mailbox instead.
 * $html:     optional HTML version (plain $body is used as the text part of the fallback).
 */
function faj_send_mail(array $config, string $to, string $subject, string $body, string $replyTo = '', ?string $html = null, string $fromAddr = ''): bool {
    $b = (array) ($config['booking'] ?? []);
    $ewsReady = ($config['mail_via'] ?? 'exchange') !== 'php'
        && !empty($b['ews_url']) && !empty($b['ews_user'])
        && !empty($b['ews_pass']) && $b['ews_pass'] !== 'PASTE_PASSWORD_HERE' && empty($b['mock']);
    if ($ewsReady) {
        $content = $html ?? $body;
        $isHtml = $html !== null;
        if ($fromAddr !== '' && strcasecmp($fromAddr, (string) $b['ews_user']) !== 0) {
            if (faj_ews_send($b, $to, $subject, $content, $replyTo, $isHtml, $fromAddr)) return true;
        }
        if (faj_ews_send($b, $to, $subject, $content, $replyTo ?: $fromAddr, $isHtml)) return true;
    }
    // Fallback: PHP mail() through Hostinger.
    $from = $fromAddr ?: ($config['mail_from'] ?? 'website@fajtech.ca');
    $h = "From: FAJTech <{$from}>\r\n" . ($replyTo ? "Reply-To: {$replyTo}\r\n" : '') . "MIME-Version: 1.0\r\n";
    if ($html !== null) {
        $bd = 'faj_' . bin2hex(random_bytes(8));
        $h .= "Content-Type: multipart/alternative; boundary=\"{$bd}\"";
        $msg = "--{$bd}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($body))
            . "--{$bd}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
            . "--{$bd}--\r\n";
    } else {
        $h .= 'Content-Type: text/plain; charset=UTF-8';
        $msg = $body;
    }
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $msg, $h, '-f' . $from);
}
