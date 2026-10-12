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
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from_number' => env('TWILIO_FROM_NUMBER'),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        // Shared secret used to authenticate Twilio status-callback webhooks.
        'webhook_token' => env('TWILIO_WEBHOOK_TOKEN'),
    ],

    'flutterwave' => [
        'secret_key' => env('FLW_SECRET_KEY'),
        'public_key' => env('FLW_PUBLIC_KEY'),
        // Set on the Flutterwave dashboard under Settings. Sent back verbatim
        // in the `verif-hash` header on webhooks.
        'secret_hash' => env('FLW_SECRET_HASH'),

        // v3 uses a single host for both sandbox and live; the API key prefix
        // (`_TEST`) selects the sandbox data store.
        'base_url' => env('FLW_BASE_URL', 'https://api.flutterwave.com/v3'),

        /*
        |----------------------------------------------------------------------
        | Amount units
        |----------------------------------------------------------------------
        |
        | Flutterwave's documentation is inconsistent about whether the `amount`
        | field is expressed in major units (12000) or minor units/cents
        | (120000), and it differs per currency. Rather than guess, the value
        | we send is stored on the payment row and compared byte-for-byte
        | against the verified transaction — so fulfilment is always correct
        | regardless of the interpretation.
        |
        | This flag controls what WE send to them. Confirm it with a single
        | sandbox transaction before going live: if the customer is charged
        | 100x, flip this to the other value.
        |
        */

        'amount_in_minor_units' => (bool) env('FLW_AMOUNT_IN_MINOR_UNITS', false),

        // How long a generated checkout link stays valid, in minutes.
        'session_duration_minutes' => (int) env('FLW_SESSION_DURATION', 30),
    ],

];
