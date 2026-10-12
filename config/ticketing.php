<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ticket Reference
    |--------------------------------------------------------------------------
    |
    | Controls the prefix and separator used when generating unique, human
    | readable ticket references (e.g. EVT-7KQD3XAB).
    |
    */

    'ticket_reference' => [
        'prefix' => env('TICKET_REFERENCE_PREFIX', 'EVT'),
        'separator' => '-',
        'length' => 8,
    ],

    /*
    |--------------------------------------------------------------------------
    | One-Time Password (OTP)
    |--------------------------------------------------------------------------
    |
    | Configuration for SMS based phone verification used during public
    | registration.
    |
    */

    'otp' => [
        'length' => (int) env('OTP_LENGTH', 6),
        'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 10),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
        'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Check-in
    |--------------------------------------------------------------------------
    */

    'checkin' => [
        // Maximum scanner sessions per user are unlimited; this caps the
        // number of recent check-ins retained in the live scanner feed.
        'scanner_feed_limit' => 50,
    ],

];