<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pitch>
 */
class PitchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => $this->faker->word() . ' Pitch',
            'location' => $this->faker->address(),
            'establishment_id' => User::where('role', 'establishment')->inRandomOrder()->first()->id ?? User::factory()->create(['role' => 'establishment'])->id,
        ];
    }
}
