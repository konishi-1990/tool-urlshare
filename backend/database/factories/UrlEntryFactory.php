<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UrlEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'       => User::factory(),
            'url'           => fake()->url(),
            'title'         => fake()->sentence(6),
            'description'   => fake()->paragraph(),
            'thumbnail_url' => fake()->imageUrl(),
            'status'        => 'temporary',
        ];
    }

    public function bookmarked(): static
    {
        return $this->state(['status' => 'bookmarked']);
    }

    public function deleted(): static
    {
        return $this->state(['status' => 'deleted']);
    }
}
