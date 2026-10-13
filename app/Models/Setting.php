<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    use LogsActivity;

    protected $fillable = [
        'key',
        'value',
    ];

    protected static function booted(): void
    {
        $forget = fn (self $setting) => Cache::forget(static::cacheKey($setting->key));

        static::saved($forget);
        static::deleted($forget);
    }

    protected static function cacheKey(string $key): string
    {
        return "setting.{$key}";
    }

    /**
     * Read a setting, falling back to $default when it is missing or blank.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = Cache::rememberForever(static::cacheKey($key), function () use ($key): ?string {
            // Defensive: the settings table may not exist yet on a very first
            // request after deploying, before `migrate` has run.
            if (! Schema::hasTable('settings')) {
                return null;
            }

            return static::query()->where('key', $key)->value('value');
        });

        return ($value === null || $value === '') ? $default : $value;
    }

    /**
     * Persist a setting (creating it if needed) and drop its cached value.
     */
    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);

        // The model events normally clear this; forgetting here keeps the
        // write path correct even if events are disabled.
        Cache::forget(static::cacheKey($key));
    }
}
