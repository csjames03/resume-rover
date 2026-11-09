<?php

namespace Database\Factories;

use App\Enums\JobSourceDriver;
use App\Models\JobSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobSource>
 */
class JobSourceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'driver' => JobSourceDriver::Greenhouse,
            'identifier' => fake()->unique()->slug(2),
            'is_enabled' => true,
            'fetch_interval_minutes' => 360,
        ];
    }
}
