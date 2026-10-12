<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Registration;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\ReferenceGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        $event = Event::factory();
        $ticketType = TicketType::factory()->for($event);

        $registration = Registration::factory()
            ->for($event)
            ->for($ticketType, 'ticketType');

        return [
            'registration_id' => $registration,
            'event_id' => $event,
            'ticket_type_id' => $ticketType,
            'reference' => app(ReferenceGenerator::class)->ticketReference(),
            'attendee_name' => fake()->name(),
            'attendee_email' => fake()->safeEmail(),
            'attendee_phone' => '+2547'.fake()->numerify('#######'),
            'status' => 'valid',
        ];
    }

    public function checkedIn(): static
    {
        return $this->state(fn () => [
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'check_in_method' => fake()->randomElement(['manual', 'qr']),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => 'cancelled']);
    }
}