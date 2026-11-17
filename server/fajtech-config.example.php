<?php
// Copy this file to the server as:
//   /home/<user>/domains/fajtech.ca/fajtech-config.php
// (one level ABOVE public_html, so it can never be downloaded).
// Do NOT commit the real file to Git.
return [
    'mail_to'   => 'info@fajtech.ca',
    'mail_from' => 'website@fajtech.ca', // must be a mailbox/domain hosted at Hostinger

    // Sender of the automatic confirmation emails to customers
    'confirm_from' => 'info@fajtech.ca',

    // Built-in consultation booking (/book-a-consultation/), connected to on-premises Exchange (EWS).
    // The booking mailbox organises each meeting and invites the customer + the consultant.
    'booking' => [
        'ews_url'      => 'https://mail.fajtech.ca/EWS/Exchange.asmx',
        'ews_user'     => 'booking@fajtech.ca',      // or DOMAIN\\booking
        'ews_pass'     => 'PASTE_PASSWORD_HERE',     // the booking mailbox password — only ever stored here
        'ews_version'  => 'Exchange2013',            // works for Exchange 2013/2016/2019
        'owner'        => 'ahmed.alfajem@fajtech.ca',// calendar checked for free/busy, invited to each meeting
        'owner_name'   => 'Ahmed Alfajem',
        'timezone'     => 'America/Edmonton',        // working hours below are in this time zone
        'days'         => [1, 2, 3, 4, 5],           // Mon–Fri
        'open'         => '09:00',
        'close'        => '17:00',
        'duration'     => 30,                        // minutes
        'buffer'       => 15,                        // free minutes kept around existing meetings
        'min_notice_hours' => 4,
        'horizon_days' => 30,
        'meeting_provider' => 'Cisco Webex',
        'meeting_link' => '',                        // e.g. your Webex Personal Room link: https://NAME.my.webex.com/meet/NAME
        'mock'         => false,                     // true = fake calendar for testing without Exchange
    ],

    // Zoho CRM → Setup → Developer Hub → Webforms → (Leads form) → Embed → copy the hidden values.
    // Leave as null to only send email.
    'zoho_web_to_lead' => null,
    /*
    'zoho_web_to_lead' => [
        'url'        => 'https://crm.zoho.ca/crm/WebToLeadForm',   // .com / .eu / .ca depending on your Zoho data centre
        'xnQsjsdp'   => 'PASTE_VALUE',
        'xmIwtLD'    => 'PASTE_VALUE',
        'actionType' => 'TGVhZHM=',
        // Field names exactly as they appear in your Zoho webform's HTML (name="...").
        'fields' => [
            'first_name'  => 'First Name',
            'last_name'   => 'Last Name',
            'email'       => 'Email',
            'phone'       => 'Phone',
            'company'     => 'Company',
            'description' => 'Description',
            'lead_source' => 'Lead Source',
            'gclid'       => null, // e.g. 'LEADCF1' if you add a custom "GCLID" field for Google Ads ↔ Zoho conversion sync
        ],
    ],
    */
];
