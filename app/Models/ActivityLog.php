<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Throwable;

class ActivityLog extends Model
{
    /** Logs are immutable — only a created timestamp is kept. */
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event',
        'subject_type',
        'subject_id',
        'description',
        'properties',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Record an activity entry.
     *
     * Logging must never break the action it is describing, so any failure is
     * swallowed and reported as a null return.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function record(
        string $event,
        ?Model $subject = null,
        ?string $description = null,
        array $properties = [],
    ): ?self {
        try {
            return static::create([
                'user_id' => auth()->id(),
                'event' => $event,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'description' => $description,
                'properties' => $properties ?: null,
                'ip_address' => app()->runningInConsole() ? null : request()->ip(),
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    /** A colour token for the event badge. */
    public function color(): string
    {
        return match ($this->event) {
            'created' => 'success',
            'updated' => 'warning',
            'deleted' => 'danger',
            'login' => 'info',
            'export' => 'primary',
            default => 'gray',
        };
    }
}
