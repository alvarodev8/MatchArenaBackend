<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Crear un administrador
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@matcharena.com',
            'role' => 'admin',
        ]);

        // Crear un jugador
        User::factory()->count(1)->create([
            'name' => 'Jugador 1',
            'email' => 'jugador@example.com',
            'password' => Hash::make('password'),
            'role' => 'player',
        ]);

        // Crear un establecimiento
        User::factory()->count(1)->create([
            'name' => 'Establecimiento 1',
            'email' => 'establecimiento@example.com',
            'password' => Hash::make('password'),
            'role' => 'establishment',
        ]);

        // Crear 5 jugadores de la factoria
        User::factory()->count(5)->create([
            'role' => 'player',
        ]);

        // Crear 3 establecimientos de la factoria
        User::factory()->count(3)->create([
            'role' => 'establishment',
        ]);
    }
}
