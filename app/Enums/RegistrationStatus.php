<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    case PendingOtp = 'pending_otp';
    case Verified = 'verified';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingOtp => 'Awaiting verification',
            self::Verified => 'Verified',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingOtp => 'warning',
            self::Verified => 'success',
            self::Cancelled => 'danger',
        };
    }
}