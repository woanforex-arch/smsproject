<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketType>
 */
class TicketTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->randomElement(['Early Bird', 'Standard', 'VIP', 'Student', 'Group']),
            'description' => fake()->sentence(),
            'price' => fake()->randomElement([0, 1500, 2500, 4500, 12000]),
            'currency' => 'KES',
            'quantity_total' => fake()->numberBetween(20, 300),
            'quantity_sold' => 0,
            'quantity_per_order_max' => 10,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function soldOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_sold' => $attributes['quantity_total'],
        ]);
    }

    public function free(): static
    {
        return $this->state(fn () => ['price' => 0]);
    }
}