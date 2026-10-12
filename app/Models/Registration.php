<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;

class Registration extends Model
{
    /** @use HasFactory<\Database\Factories\RegistrationFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'event_id',
        'ticket_type_id',
        'user_id',
        'reference',
        'name',
        'email',
        'phone',
        'quantity',
        'amount',
        'currency',
        'status',
        'payment_status',
        'otp_attempts',
        'otp_verified_at',
        'ip_address',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => RegistrationStatus::class,
            'otp_verified_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function smsMessages(): HasMany
    {
        return $this->hasMany(SmsMessage::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany('id');
    }

    public function isVerified(): bool
    {
        return $this->status === RegistrationStatus::Verified;
    }

    /**
     * Whether this booking still owes money.
     *
     * Free ticket types never require payment.
     */
    public function requiresPayment(): bool
    {
        return (float) $this->amount > 0
            && (bool) ($this->ticketType?->requires_payment ?? false);
    }

    public function isPaid(): bool
    {
        return ! $this->requiresPayment() || $this->payment_status === 'paid';
    }

    /** Fully complete: verified, settled and tickets issued. */
    public function isComplete(): bool
    {
        return $this->isVerified() && $this->isPaid() && $this->tickets()->exists();
    }
}