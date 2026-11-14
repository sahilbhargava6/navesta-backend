<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    // MapmyIndia (Mappls) geocoding, used to verify pincodes and autofill
    // city/state. Static REST key from the Mappls console; restrict it to the
    // server's IP there. Server-side only, never sent to the browser.
    'mappls' => [
        'static_key' => env('MAPPLS_STATIC_KEY'),
        'geocode_url' => env('MAPPLS_GEOCODE_URL', 'https://search.mappls.com/search/address/geocode'),
        'timeout' => (int) env('MAPPLS_TIMEOUT', 3),
    ],

    // Backup pincode directory when Mappls is not configured or not answering
    'india_post' => [
        'pincode_url' => env('INDIA_POST_PINCODE_URL', 'https://api.postalpincode.in/pincode/'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
