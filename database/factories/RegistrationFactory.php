<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Services\ReferenceGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Registration>
 */
class RegistrationFactory extends Factory
{
    public function definition(): array
    {
        $event = Event::factory()->state(['status' => EventStatus::Published]);
        $ticketType = TicketType::factory()->for($event);
        $quantity = fake()->numberBetween(1, 3);

        return [
            'event_id' => $event,
            'ticket_type_id' => $ticketType,
            'reference' => app(ReferenceGenerator::class)->registrationReference(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+2547'.fake()->unique()->numerify('#######'),
            'quantity' => $quantity,
            'amount' => fake()->randomFloat(2, 1000, 50000),
            'currency' => 'KES',
            'status' => RegistrationStatus::Verified,
            'otp_verified_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => RegistrationStatus::PendingOtp,
            'otp_verified_at' => null,
        ]);
    }
}