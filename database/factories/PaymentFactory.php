<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Registration;
use App\Services\ReferenceGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        $registration = Registration::factory();

        return [
            'registration_id' => $registration,
            'provider' => 'flutterwave',
            'tx_ref' => app(ReferenceGenerator::class)->paymentReference(fake()->unique()->bothify('REG-???????')),
            'amount' => fake()->randomFloat(2, 1000, 50000),
            'currency' => 'KES',
            'status' => PaymentStatus::Pending,
            'checkout_url' => 'https://checkout.flutterwave.com/v3/hosted/pay/flwlnk-'.fake()->bothify('?????#??#??#?'),
            'attempts' => 1,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Successful,
            'provider_status' => 'successful',
            'transaction_id' => (string) fake()->numberBetween(100000, 999999),
            'payment_type' => fake()->randomElement(['mpesa', 'card', 'banktransfer']),
            'paid_at' => now(),
            'verified_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Failed,
            'failure_reason' => 'Insufficient funds.',
            'verified_at' => now(),
        ]);
    }
}