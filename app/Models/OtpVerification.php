<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
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
        return $this->belongsTo(Registration::class, 'context');
    }
}