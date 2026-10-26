<?php

/**
 * Plugin configuration. Values default to the legacy SURVEY_* environment
 * variables, so existing installations keep working unchanged.
 *
 * Override in config/renick/survey/config.php of the October installation.
 */
return [
    // Block a second submission from the same browser session
    'cookie_limit' => env('SURVEY_COOKIE_LIMIT', true),

    // Max submissions per IP address and survey (0 or lower = no IP limit)
    'ip_address_limit' => env('SURVEY_IP_ADDRESS_LIMIT', 1),

    // Reject a second submission with the same e-mail address
    'unique_email' => env('SURVEY_UNIQUE_EMAIL', true),

    // Do not send notification e-mails to survey owners
    'disable_email_notifications' => env('SURVEY_DISABLE_EMAIL_NOTIFICATIONS', false),

    // Max submission attempts per minute per IP address and survey
    'throttle_per_minute' => env('SURVEY_THROTTLE_PER_MINUTE', 20),

    // Export format: 'auto' (xlsx, csv without the PHP zip extension) or 'csv' to always export csv
    'export_format' => env('SURVEY_EXPORT_FORMAT', 'auto'),
];
