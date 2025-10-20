<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->sentence(3);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'short_description' => $this->faker->sentence(12),
            'department' => $this->faker->randomElement(['Secretaria General', 'Direccion Academica', 'Oficina de Admision']),
            'category' => $this->faker->randomElement(['Tramites documentarios', 'Servicios academicos', 'Tramites estudiantiles']),
            'usage_count' => $this->faker->numberBetween(200, 2000),
            'coverage' => $this->faker->randomElement(['Toda la institucion', 'Estudiantes regulares', 'Facultad de Ingenieria']),
            'url' => $this->faker->url(),
            'type' => $this->faker->randomElement(['api-rest', 'form-web', 'archivo-batch', 'proceso-manual']),
            'status' => $this->faker->randomElement(['borrador', 'revision', 'aprobado']),
            'auth_type' => $this->faker->randomElement(['oauth2', 'sso', 'api_key', 'ninguna']),
            'schedule' => $this->faker->randomElement(['office', 'full']),
            'monthly_limit' => $this->faker->randomElement([null, 1500, 3200, 5000]),
            'tags' => $this->faker->randomElements(['recientes', 'masUsados', 'destacados'], $this->faker->numberBetween(1, 3)),
            'labels' => $this->faker->randomElements(['Atencion 24/7', 'Entrega digital', 'Pago en linea', 'Disponible en PDF'], 2),
            'owner' => $this->faker->company(),
            'documentation_url' => $this->faker->url(),
            'terms_accepted' => true,
            'approved_at' => $this->faker->randomElement([null, now()->subDays(rand(1, 30))]),
        ];
    }
}
