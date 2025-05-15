<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Reservation;

class PlayerController extends Controller
{
    public function profile(Request $request)
    {
        $user = Auth::user();
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ]);
    }

    public function reservations(Request $request)
    {
        $reservations = Reservation::where('player_id', auth()->id())
            ->with('pitch')
            ->get()
            ->map(function ($reservation) {
                return [
                    'id' => $reservation->id,
                    'start_at' => $reservation->start_at,
                    'duration' => $reservation->duration,
                    'cancelled_at' => $reservation->cancelled_at,
                    'is_cancelled' => $reservation->cancelled_at !== null,
                    'pitch' => [
                        'id' => $reservation->pitch->id,
                        'name' => $reservation->pitch->name,
                        'location' => $reservation->pitch->location,
                    ],
                ];
            });

        return response()->json($reservations);
    }
}
