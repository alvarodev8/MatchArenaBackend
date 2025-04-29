<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

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

        // Crear 5 jugadores
        User::factory()->count(5)->create([
            'role' => 'player',
        ]);

        // Crear 3 establecimientos
        User::factory()->count(3)->create([
            'role' => 'establishment',
        ]);
    }
}
