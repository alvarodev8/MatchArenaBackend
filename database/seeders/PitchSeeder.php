<?php

namespace Database\Seeders;

use App\Models\Pitch;
use App\Models\User;
use Illuminate\Database\Seeder;

class PitchSeeder extends Seeder
{
    public function run(): void
    {
        $establishment = User::where('role', 'establishment')->first();

        if ($establishment) {
            Pitch::create([
                'name' => 'Main Pitch',
                'location' => '123 Sports Ave',
                'price' => 15,
                'description' => 'The main pitch for all major events.',
                'establishment_id' => $establishment->id,
            ]);
        }
    }
}
