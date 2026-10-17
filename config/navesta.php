<?php

return [

    /*
    | Month boundaries (debt snapshots) and reminder dates follow Indian time.
    */
    'timezone' => env('NAVESTA_TIMEZONE', 'Asia/Kolkata'),

    /*
    | Rate used to estimate refinancing savings when no active lender can
    | take over a loan.
    */
    'fallback_benchmark_rate_percent' => 8.5,

    /*
    | Payoff plans: card minimum due when the customer has not entered one
    | (share of the starting balance, with a floor), and the simulation cap.
    */
    'payoff' => [
        'card_min_payment_percent' => 5,
        'card_min_payment_floor_rupees' => 200,
        'max_months' => 600,
    ],

    /*
    | Lists returned by the API are paginated.
    */
    'pagination' => [
        'default_per_page' => 50,
        'max_per_page' => 100,
    ],

    /*
    | Payment reminders: how many days before a due date they go out.
    */
    'reminders' => [
        'days_before_due' => (int) env('REMINDER_DAYS_BEFORE_DUE', 3),
    ],

    /*
    | WhatsApp / SMS delivery. "log" writes messages to the log (development);
    | "twilio" sends them for real.
    */
    'messaging' => [
        'driver' => env('MESSAGING_DRIVER', 'log'),
        'twilio' => [
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'sms_from' => env('TWILIO_SMS_FROM'),
            'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
        ],
    ],

    /*
    | Uploaded bank/card statements: storage disk, size limit and virus
    | scanning. Scanner "clamav" talks to a clamd daemon; "none" skips
    | scanning (development only — files are marked "unscanned").
    */
    'statements' => [
        'disk' => env('STATEMENTS_DISK', 'statements'),
        'max_upload_kb' => 10240,
        'scanner' => env('STATEMENT_SCANNER', 'none'),
        'clamav' => [
            'host' => env('CLAMAV_HOST', '127.0.0.1'),
            'port' => (int) env('CLAMAV_PORT', 3310),
            'timeout' => (int) env('CLAMAV_TIMEOUT', 30),
        ],
    ],

    /*
    | Public media (blog cover images).
    */
    'media_disk' => env('MEDIA_DISK', 'public'),

    /*
    | API requests per minute per customer (per IP for guests). Raise it on
    | staging while load testing.
    */
    'api_rate_limit' => (int) env('API_RATE_LIMIT', 100),

    /*
    | Sign-in protection: after `max_failures` wrong passwords for one email
    | within `lockout_minutes`, that email is locked for `lockout_minutes`.
    */
    'auth' => [
        'max_failures' => 10,
        'lockout_minutes' => 15,
    ],

    /*
    | One-time codes sent by SMS (phone verification, sign-in second step).
    */
    'otp' => [
        'length' => 6,
        'ttl_minutes' => 10,
        'max_attempts' => 5,
    ],

    /*
    | Versions of the notices customers agree to. Bump a version when the
    | text changes; each consent row records the version that was shown.
    */
    'consent' => [
        'terms' => env('NOTICE_VERSION_TERMS', '2026-10'),
        'privacy' => env('NOTICE_VERSION_PRIVACY', '2026-10'),
        'reminders' => env('NOTICE_VERSION_REMINDERS', '2026-10'),
    ],

    /*
    | How long data is kept before it is deleted automatically.
    */
    'retention' => [
        'audit_log_days' => (int) env('RETENTION_AUDIT_LOG_DAYS', 730),
        'statement_days' => (int) env('RETENTION_STATEMENT_DAYS', 365),
    ],

    /*
    | Per-customer results (dashboard, plans, matches) are cached and dropped
    | as soon as the customer's cards, loans or scores change.
    */
    'cache' => [
        'user_results_minutes' => (int) env('USER_CACHE_MINUTES', 10),
    ],

];
