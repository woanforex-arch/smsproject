<?php

namespace App\Filament\Widgets;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Ticket;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class TicketingStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Ticketing overview';

    protected ?string $description = 'Live totals across every event.';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $ticketsSold = Ticket::whereIn('status', ['valid', 'checked_in'])->count();
        $checkedIn = Ticket::where('status', 'checked_in')->count();

        $revenue = (float) Registration::where('status', 'verified')->sum('amount');

        $pending = Registration::where('status', 'pending_otp')->count();

        $smsFailures = \App\Models\SmsMessage::whereIn('status', ['failed', 'undelivered'])->count();

        $upcoming = Event::where('status', EventStatus::Published->value)
            ->where('ends_at', '>=', now())
            ->count();

        $attendanceRate = $ticketsSold > 0 ? round(($checkedIn / $ticketsSold) * 100, 1) : 0.0;

        return [
            Stat::make('Tickets sold', number_format($ticketsSold))
                ->description('Across all events')
                ->descriptionIcon('heroicon-m-ticket')
                ->color('primary'),

            Stat::make('Checked in', number_format($checkedIn))
                ->description($attendanceRate.'% attendance rate')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('Revenue', number_format($revenue, 2))
                ->description('Verified registrations')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info'),

            Stat::make('Awaiting OTP', number_format($pending))
                ->description('Unverified registrations')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pending > 0 ? 'warning' : 'success'),

            Stat::make('SMS failures', number_format($smsFailures))
                ->description('Failed or undelivered')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($smsFailures > 0 ? 'danger' : 'success'),

            Stat::make('Upcoming events', number_format($upcoming))
                ->description('Published and not finished')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary'),
        ];
    }
}