<?php
// Copy this file to the server as:
//   /home/<user>/domains/fajtech.ca/fajtech-config.php
// (one level ABOVE public_html, so it can never be downloaded).
// Do NOT commit the real file to Git.
return [
    'mail_to'   => 'info@fajtech.ca',
    'mail_from' => 'website@fajtech.ca', // must be a mailbox/domain hosted at Hostinger

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
