<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ServiceVersion>
 */
class ServiceVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'version' => $this->faker->randomElement(['1.0.0', '1.1.0', '2.0.0', '2.1.0']),
            'status' => $this->faker->randomElement(['available', 'maintenance', 'deprecated']),
            'release_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'compatibility' => $this->faker->randomElement(['Web', 'Web / Movil', 'Web / Punto de atencion']),
            'documentation_url' => $this->faker->url(),
            'is_requestable' => $this->faker->boolean(80),
            'limit_suggestion' => $this->faker->randomElement([null, 1200, 2500, 4000]),
            'notes' => $this->faker->sentence(10),
        ];
    }
}
