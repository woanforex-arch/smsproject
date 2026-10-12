<?php

namespace App\Enums;

enum SmsStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Undelivered = 'undelivered';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued => 'gray',
            self::Sent => 'info',
            self::Delivered => 'success',
            self::Undelivered, self::Failed => 'danger',
        };
    }

    public function isFailure(): bool
    {
        return in_array($this, [self::Failed, self::Undelivered], true);
    }
}