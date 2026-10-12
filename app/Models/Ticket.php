<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    /** @use HasFactory<\Database\Factories\TicketFactory> */
    use HasFactory;

    protected $fillable = [
        'registration_id',
        'event_id',
        'ticket_type_id',
        'reference',
        'attendee_name',
        'attendee_email',
        'attendee_phone',
        'status',
        'checked_in_at',
        'checked_in_by',
        'check_in_method',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'checked_in_at' => 'datetime',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function isValid(): bool
    {
        return $this->status === TicketStatus::Valid;
    }

    public function isCheckedIn(): bool
    {
        return $this->status === TicketStatus::CheckedIn;
    }

    public function isCancelled(): bool
    {
        return $this->status === TicketStatus::Cancelled;
    }

    /**
     * The payload embedded in the QR code.
     *
     * Format: "{reference}.{signature}" where the signature is an HMAC of the
     * reference keyed with the app encryption key. The scanner verifies the
     * signature before hitting the database, so forged codes are rejected
     * cheaply without a lookup.
     */
    public function qrPayload(): string
    {
        return $this->reference.'.'.static::signReference($this->reference);
    }

    /** Produce the short, deterministic signature for a ticket reference. */
    public static function signReference(string $reference): string
    {
        return substr(
            hash_hmac('sha256', $reference, (string) config('app.key')),
            0,
            16
        );
    }

    /** Determine whether a scanned QR payload carries a valid signature. */
    public static function isValidPayload(?string $payload): bool
    {
        if (! is_string($payload) || ! str_contains($payload, '.')) {
            return false;
        }

        [$reference, $signature] = explode('.', $payload, 2);

        return $reference !== ''
            && hash_equals(static::signReference($reference), $signature);
    }

    /** Extract the bare reference from a scanned QR payload. */
    public static function referenceFromPayload(string $payload): string
    {
        return explode('.', $payload, 2)[0];
    }
}