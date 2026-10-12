<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'registration_id',
        'user_id',
        'provider',
        'tx_ref',
        'transaction_id',
        'flw_ref',
        'amount',
        'currency',
        'status',
        'provider_status',
        'payment_type',
        'checkout_url',
        'attempts',
        'failure_reason',
        'paid_at',
        'verified_at',
        'verified_by',
        'meta',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'meta' => 'array',
            'raw_payload' => 'array',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Successful;
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    /** A payment may still be completed by the guest while it is pending. */
    public function canBePaid(): bool
    {
        return $this->isPending();
    }
}