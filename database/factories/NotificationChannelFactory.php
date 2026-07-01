<?php

namespace Database\Factories;

use App\Enums\NotificationDriver;
use App\Models\NotificationChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationChannel>
 */
class NotificationChannelFactory extends Factory
{
    protected $model = NotificationChannel::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Alert',
            'driver' => NotificationDriver::Email,
            'config' => [
                'recipients' => fake()->safeEmail(),
            ],
            'is_active' => true,
            'notify_on_success' => true,
            'notify_on_failure' => true,
        ];
    }

    public function email(string $recipients = 'admin@example.com'): static
    {
        return $this->state(fn () => [
            'driver' => NotificationDriver::Email,
            'config' => ['recipients' => $recipients],
        ]);
    }

    public function whatsapp(): static
    {
        return $this->state(fn () => [
            'driver' => NotificationDriver::WhatsApp,
            'config' => [
                'api_url' => 'https://api.example.com/send',
                'api_token' => 'test-token',
                'recipient' => '6281234567890',
            ],
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function successOnly(): static
    {
        return $this->state(fn () => [
            'notify_on_success' => true,
            'notify_on_failure' => false,
        ]);
    }

    public function failureOnly(): static
    {
        return $this->state(fn () => [
            'notify_on_success' => false,
            'notify_on_failure' => true,
        ]);
    }
}
