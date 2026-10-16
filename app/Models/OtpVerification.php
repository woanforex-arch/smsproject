<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    use MassPrunable;

    protected $fillable = [
        'phone',
        'code_hash',
        'purpose',
        'context',
        'attempts',
        'expires_at',
        'verified_at',
        'last_sent_at',
        'ip_address',
    ];

    protected $hidden = [
        'code_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    public static function hashCode(string $code): string
    {
        return hash('sha256', $code);
    }

    public function matches(string $code): bool
    {
        return hash_equals((string) $this->code_hash, static::hashCode($code));
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function hasAttemptsLeft(int $max): bool
    {
        return $this->attempts < $max;
    }

    public function isUsable(int $maxAttempts): bool
    {
        return ! $this->isVerified()
            && ! $this->isExpired()
            && $this->hasAttemptsLeft($maxAttempts);
    }

    public function registration()
    {
        return $this->belongsTo(Registration::class, 'context', 'reference');
    }

    /**
     * Rows to remove with `model:prune` — anything older than a week is past
     * its usefulness (codes expire in minutes) and only grows the table.
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subWeek());
    }
}