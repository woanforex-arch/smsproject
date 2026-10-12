<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly Registration $registration,
        public readonly Event $event,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticketCount = $this->registration->tickets->count();

        $message = (new MailMessage)
            ->subject("Reminder: {$this->event->title} is coming up")
            ->greeting("Hi {$this->registration->name},")
            ->line("Just a reminder that {$this->event->title} is coming up.")
            ->line("**When:** {$this->event->starts_at->format('l, j F Y \a\t g:i a')}")
            ->line("**Where:** {$this->event->venue}".($this->event->city ? ", {$this->event->city}" : ''))
            ->line("You hold {$ticketCount} ticket(s).");

        $firstTicket = $this->registration->tickets->first();

        if ($firstTicket) {
            $message->action('View my ticket', route('tickets.show', $firstTicket->reference));
        }

        return $message
            ->line('Please bring your e-ticket — the QR code on screen or printed will speed things up at the door.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'event_id' => $this->event->id,
            'registration_id' => $this->registration->id,
        ];
    }
}