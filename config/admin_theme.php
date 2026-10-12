<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin panel branding
    |--------------------------------------------------------------------------
    |
    | Central place for the look and feel of the Filament admin panel. Change
    | the hex value here and the whole interface updates — no Blade or CSS
    | edits required.
    |
    */

    'brand' => [
        'name' => env('ADMIN_BRAND_NAME', 'Event Tickets'),

        // Wordmark shown next to the logo in the sidebar.
        'tagline' => env('ADMIN_BRAND_TAGLINE', 'Ticketing Operations'),

        // Navy: a conventional, trustworthy corporate accent.
        'primary' => env('ADMIN_BRAND_PRIMARY', '#1e3a5f'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Colour tokens
    |--------------------------------------------------------------------------
    |
    | Only `primary` is driven by Filament's colour system. The rest are plain
    | CSS custom properties consumed by the theme stylesheet, which is how the
    | surfaces, borders and text tones stay consistent across every page.
    |
    */

    'palette' => [
        'navy' => '#1e3a5f',
        'navy-dark' => '#152c49',
        'navy-light' => '#2c5182',

        'ink' => '#0f172a',
        'body' => '#334155',
        'muted' => '#64748b',
        'faint' => '#94a3b8',

        'surface' => '#ffffff',
        'canvas' => '#f6f8fb',
        'border' => '#e2e8f0',
        'border-strong' => '#cbd5e1',

        'positive' => '#15803d',
        'warning' => '#b45309',
        'negative' => '#b91c1c',
        'info' => '#1d4ed8',
    ],

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    */

    'layout' => [
        // Constrains the working area so content does not stretch across very
        // wide monitors, which is what makes a dashboard read as "corporate".
        'max_content_width' => 'full',

        'sidebar_collapsible_on_desktop' => true,
    ],

];