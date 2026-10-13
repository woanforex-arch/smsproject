<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Central money formatting.
 *
 * The currency *code* (e.g. KES) is stored on individual records so exports
 * and gateway payloads stay accurate, while the *sign* shown to humans is a
 * single admin-editable setting. Change it once and every screen follows.
 */
class Money
{
    /** Default code used when creating new ticket types. */
    public static function code(): string
    {
        return Setting::get('currency.code', 'KES') ?? 'KES';
    }

    /** The sign rendered next to amounts; falls back to the code. */
    public static function symbol(): string
    {
        return Setting::get('currency.symbol') ?: static::code();
    }

    /** Whether the sign sits before or after the number. */
    public static function position(): string
    {
        return Setting::get('currency.position', 'before') === 'after' ? 'after' : 'before';
    }

    /** Format an amount with the configured sign, e.g. "2,500.00 KSh". */
    public static function format(float|int|string|null $amount, int $decimals = 2): string
    {
        $value = number_format((float) $amount, $decimals);
        $symbol = static::symbol();

        return static::position() === 'before'
            ? trim("{$symbol} {$value}")
            : trim("{$value} {$symbol}");
    }
}
