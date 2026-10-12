<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Valid = 'valid';
    case CheckedIn = 'checked_in';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Valid',
            self::CheckedIn => 'Checked in',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Valid => 'success',
            self::CheckedIn => 'info',
            self::Cancelled => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Valid => 'heroicon-o-ticket',
            self::CheckedIn => 'heroicon-o-check-badge',
            self::Cancelled => 'heroicon-o-x-circle',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}