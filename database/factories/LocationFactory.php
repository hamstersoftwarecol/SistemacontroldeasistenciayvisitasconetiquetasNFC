<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Habitación '.fake()->unique()->numberBetween(100, 999),
            'code' => Str::upper(Str::random(6)),
            'area' => fake()->randomElement(['Torre A', 'Torre B', 'Recepción', 'Bodega']),
            'floor' => (string) fake()->numberBetween(1, 5),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
