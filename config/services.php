<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        // A single incoming webhook URL for the family's Slack workspace —
        // there's only ever one channel, so no per-user routing is needed.
        'webhook_url' => env('SLACK_WEBHOOK_URL'),
    ],

    'nominatim' => [
        // Nominatim's usage policy requires a User-Agent that identifies the
        // app and gives a way to contact its operator — override this in
        // .env with a real contact (e.g. "Family Tree (you@example.com)").
        'user_agent' => env('NOMINATIM_USER_AGENT', config('app.name').' ('.config('app.url').')'),
    ],

];
