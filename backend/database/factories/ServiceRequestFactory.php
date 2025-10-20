<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'service_version_id' => ServiceVersion::factory(),
            'consumer_name' => $this->faker->name(),
            'consumer_email' => $this->faker->safeEmail(),
            'schedule' => $this->faker->randomElement(['office', 'full', 'custom']),
            'custom_start' => '09:00',
            'custom_end' => '15:00',
            'monthly_limit' => $this->faker->numberBetween(500, 5000),
            'notes' => $this->faker->sentence(12),
            'status' => 'pending',
        ];
    }
}
