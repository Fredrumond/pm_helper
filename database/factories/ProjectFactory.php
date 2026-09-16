<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'repository' => sprintf(
                'acme/%s',
                fake()->unique()->regexify('[a-z]{5}-[a-z]{5}'),
            ),
            'branch' => 'main',
        ];
    }
}
