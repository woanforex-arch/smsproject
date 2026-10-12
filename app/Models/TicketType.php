<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
    /** @use HasFactory<\Database\Factories\TicketTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'price',
        'currency',
        'requires_payment',
        'quantity_total',
        'quantity_sold',
        'quantity_per_order_max',
        'sales_start_at',
        'sales_end_at',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'requires_payment' => 'boolean',
            'sales_start_at' => 'datetime',
            'sales_end_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function hasUnlimitedStock(): bool
    {
        return $this->quantity_total === 0;
    }

    /** A ticket type only charges if it costs something and payment is enabled. */
    public function needsPayment(): bool
    {
        return (float) $this->price > 0 && $this->requires_payment;
    }

    public function quantityRemaining(): int
    {
        if ($this->hasUnlimitedStock()) {
            return PHP_INT_MAX;
        }

        return max(0, $this->quantity_total - $this->quantity_sold);
    }

    public function isSoldOut(): bool
    {
        return $this->quantityRemaining() <= 0;
    }

    public function isOnSale(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->sales_start_at !== null && $this->sales_start_at->isFuture()) {
            return false;
        }

        if ($this->sales_end_at !== null && $this->sales_end_at->isPast()) {
            return false;
        }

        return ! $this->isSoldOut();
    }
}