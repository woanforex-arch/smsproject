<?php

namespace App\Models;

use App\Enums\SmsStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsMessage extends Model
{
    protected $fillable = [
        'registration_id',
        'ticket_id',
        'type',
        'to_phone',
        'body',
        'provider_sid',
        'status',
        'error_code',
        'error_message',
        'attempts',
        'sent_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SmsStatus::class,
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}