<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 week', '+6 months');

        $title = fake()->unique()->catchPhrase();

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1000, 99999),
            'description' => fake()->paragraph(),
            'venue' => fake()->company().' Hall',
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+4 hours'),
            'registration_deadline' => (clone $startsAt)->modify('-1 day'),
            'status' => EventStatus::Published,
            'capacity' => fake()->numberBetween(50, 1000),
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Draft]);
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Published]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => EventStatus::Closed,
            'registration_deadline' => now()->subDay(),
        ]);
    }

    public function past(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subDays(7),
            'ends_at' => now()->subDays(7)->addHours(4),
        ]);
    }
}