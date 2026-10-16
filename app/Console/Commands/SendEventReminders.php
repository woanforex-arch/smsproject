<?php

namespace App\Console\Commands;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Ticket;
use App\Notifications\EventReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class SendEventReminders extends Command
{
    protected $signature = 'events:send-reminders
                            {--hours=24 : How many hours before the event to send the reminder}';

    protected $description = 'Email registered guests a reminder ahead of their event.';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $now = Carbon::now();
        $target = $now->copy()->addHours($hours);

        $events = Event::query()
            ->published()
            ->where('starts_at', '>', $now)
            ->where('starts_at', '<=', $target)
            ->with(['registrations.tickets'])
            ->get();

        if ($events->isEmpty()) {
            $this->info("No events starting within {$hours} hours.");

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($events as $event) {
            $registrations = $event->registrations
                ->where('status', 'verified')
                ->whereNull('reminder_sent_at')
                ->filter(fn (Registration $r) => $r->tickets->isNotEmpty());

            foreach ($registrations as $registration) {
                // Registrants are guests, so notify on demand rather than
                // through a User model.
                Notification::route('mail', $registration->email)
                    ->notify(new EventReminder($registration, $event));

                // Stamp the send so the next (hourly) run skips this guest.
                $registration->update(['reminder_sent_at' => now()]);

                $sent++;
            }

            $this->info(sprintf(
                '%s — %d registration(s), %d ticket(s) sold.',
                $event->title,
                $registrations->count(),
                $event->tickets()->whereIn('status', ['valid', 'checked_in'])->count(),
            ));
        }

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}