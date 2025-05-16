<?php

namespace App\Http\Controllers;

use App\Models\Pitch;
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

    public function createReservation(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'pitch_id' => 'required|exists:pitches,id',
            'start_at' => 'required|date|after:now',
            'duration' => 'required|integer|min:30|max:120',
        ]);

        // Verificar si el horario está disponible
        $existingReservation = Reservation::where('pitch_id', $validated['pitch_id'])
            ->where('start_at', '<=', $validated['start_at'])
            ->whereRaw('DATE_ADD(start_at, INTERVAL duration MINUTE) > ?', [$validated['start_at']])
            ->whereNull('cancellation_date')
            ->exists();

        if ($existingReservation) {
            return response()->json(['error' => 'El horario seleccionado ya está reservado.'], 400);
        }

        $pitch = Pitch::find($validated['pitch_id']);
        $price = $pitch->price ?? 30.00;

        $reservation = Reservation::create([
            'player_id' => $user->id,
            'pitch_id' => $validated['pitch_id'],
            'start_at' => $validated['start_at'],
            'duration' => $validated['duration'],
            'price' => $price,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => null,
        ]);

        return response()->json([
            'message' => 'Reserva creada con éxito.',
            'reservation' => [
                'id' => $reservation->id,
                'start_at' => $reservation->start_at,
                'duration' => $reservation->duration,
                'price' => $reservation->price,
                'pitch' => [
                    'id' => $pitch->id,
                    'name' => $pitch->name,
                    'location' => $pitch->location,
                ],
            ]
        ], 201);
    }
}
