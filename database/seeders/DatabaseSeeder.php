<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\Fixture;
use App\Models\Pitch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(UserSeeder::class);

        // Crear usuarios
        $player = User::create([
            'name' => 'Jugador 1',
            'email' => 'jugador@example.com',
            'password' => Hash::make('password'),
            'role' => 'player',
        ]);

        $establishment = User::create([
            'name' => 'Establecimiento 1',
            'email' => 'establecimiento@example.com',
            'password' => Hash::make('password'),
            'role' => 'establishment',
        ]);

        $admin = User::create([
            'name' => 'Admin 1',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        // Crear pitches
        $pitch1 = Pitch::create([
            'name' => 'Campo 1',
            'location' => 'Madrid',
            'establishment_id' => $establishment->id,
        ]);

        $pitch2 = Pitch::create([
            'name' => 'Campo 2',
            'location' => 'Barcelona',
            'establishment_id' => $establishment->id,
        ]);

        // Crear fixtures
        Fixture::create([
            'player_id' => $player->id,
            'pitch_id' => $pitch1->id,
            'date' => now()->addDays(1),
            'opponent' => 'Equipo A',
        ]);

        Fixture::create([
            'player_id' => $player->id,
            'pitch_id' => $pitch2->id,
            'date' => now()->addDays(2),
            'opponent' => 'Equipo B',
        ]);
    }
}
