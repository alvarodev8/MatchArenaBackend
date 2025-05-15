<?php

namespace Database\Seeders;

use App\Models\Pitch;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ReservationSeeder extends Seeder
{
    public function run(): void
    {
      $players = User::where('role', 'player')->get();
        $pitches = Pitch::all();

        // Crear 5 reservas confirmadas o pendientes
        foreach (range(1, 5) as $i) {
            $player = $players->random();
            $pitch = $pitches->random();

            Reservation::create([
                'player_id' => $player->id,
                'pitch_id' => $pitch->id,
                'start_at' => Carbon::now()->addDays(rand(1, 15))->setTime(rand(8, 20), 0),
                'duration' => rand(60, 120),
                'price' => rand(20, 50),
                'status' => fake()->randomElement(['pending', 'confirmed']),
                'payment_status' => fake()->randomElement(['pending', 'completed']),
                'payment_method' => fake()->randomElement(['card', 'paypal', 'cash']),
            ]);
        }

        // Crear 3 reservas canceladas
        foreach (range(1, 3) as $i) {
            $player = $players->random();
            $pitch = $pitches->random();

            Reservation::create([
                'player_id' => $player->id,
                'pitch_id' => $pitch->id,
                'start_at' => Carbon::now()->subDays(rand(1, 10))->setTime(rand(10, 18), 0),
                'duration' => rand(60, 120),
                'price' => rand(20, 50),
                'status' => 'cancelled',
                'payment_status' => fake()->randomElement(['pending', 'completed', 'failed']),
                'payment_method' => fake()->randomElement(['card', 'cash']),
                'cancellation_reason' => fake()->sentence(),
                'cancellation_date' => Carbon::now()->subDays(rand(1, 5)),
            ]);
        }
    }
}
