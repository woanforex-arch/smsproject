<?php

namespace App\Notifications;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketIssued extends Notification implements ShouldQueue
{
    use Queueable;

    /** Send after the HTTP response so registration stays fast. */
    public int $tries = 3;

    public function __construct(public readonly Registration $registration) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $registration = $this->registration->loadMissing(['event', 'tickets', 'ticketType']);
        $event = $registration->event;

        $message = (new MailMessage)
            ->subject("Your e-ticket for {$event->title}")
            ->greeting("Hi {$registration->name},")
            ->line('Your registration is confirmed. Keep this email — you can show the QR code below at the door.')
            ->line("**Event:** {$event->title}")
            ->line("**When:** {$event->starts_at->format('l, j F Y \a\t g:i a')}")
            ->line("**Where:** {$event->venue}".($event->city ? ", {$event->city}" : ''))
            ->line("**Booking reference:** {$registration->reference}");

        $message->line('');
        $message->line('Your tickets:');

        foreach ($registration->tickets as $ticket) {
            $message->line("• {$ticket->reference} — {$ticket->ticketType->name}");
        }

        $message->line('');
        $message->line('You can also view and print your tickets here:');

        $firstTicket = $registration->tickets->first();

        if ($firstTicket) {
            $message->line(route('tickets.show', $firstTicket->reference));
        } else {
            $message->line('Tickets have not been issued for this booking yet — please contact the organiser.');
        }

        return $message;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'registration_id' => $this->registration->id,
            'reference' => $this->registration->reference,
        ];
    }
}