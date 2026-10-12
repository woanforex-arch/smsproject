<?php

namespace App\Models;

use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'venue',
        'address',
        'city',
        'starts_at',
        'ends_at',
        'registration_deadline',
        'status',
        'capacity',
        'banner_path',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_deadline' => 'datetime',
            'status' => EventStatus::class,
        ];
    }

    // Relations

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class)->orderBy('sort_order');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    // Scopes

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', EventStatus::Published);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('ends_at', '>=', now());
    }

    // Helpers

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'event';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function isOpenForRegistration(): bool
    {
        if ($this->status !== EventStatus::Published) {
            return false;
        }

        if ($this->registration_deadline !== null && $this->registration_deadline->isPast()) {
            return false;
        }

        if ($this->ends_at->isPast()) {
            return false;
        }

        return $this->hasAvailability();
    }

    public function hasAvailability(): bool
    {
        if ($this->capacity === null) {
            return true;
        }

        return $this->tickets()->whereIn('status', ['valid', 'checked_in'])->count() < $this->capacity;
    }

    public function soldCount(): int
    {
        return $this->tickets()->whereIn('status', ['valid', 'checked_in'])->count();
    }

    public function checkedInCount(): int
    {
        return $this->tickets()->where('status', 'checked_in')->count();
    }

    public function revenue(): float
    {
        return (float) $this->registrations()->where('status', 'verified')->sum('amount');
    }

    public function totalCapacity(): int
    {
        return (int) $this->ticketTypes()->sum('quantity_total');
    }

    /**
     * The display currency for this event, taken from its first ticket type.
     */
    public function currency(): string
    {
        return $this->ticketTypes->first()?->currency ?: 'KES';
    }
}