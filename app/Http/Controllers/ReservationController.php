<?php

namespace App\Http\Controllers;

use App\Models\Pitch;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{

    /**
     * Lista las reservas del jugador
     */
    public function index(Request $request)
    {
        $reservations = Reservation::where('player_id', auth()->id())
            ->with('pitch')
            ->get()
            ->map(function ($reservation) {
                return [
                    'id' => $reservation->id,
                    'start_at' => $reservation->start_at,
                    'duration' => $reservation->duration,
                    'cancelled_at' => $reservation->cancellation_date,
                    'is_cancelled' => $reservation->cancellation_date !== null,
                    'pitch' => [
                        'id' => $reservation->pitch->id,
                        'name' => $reservation->pitch->name,
                        'location' => $reservation->pitch->location,
                    ],
                ];
            });

        return response()->json($reservations);
    }

    /**
     * Crea una nueva reserva para el jugador
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        try {
            $validated = $request->validate([
                'pitch_id' => 'required|exists:pitches,id',
                'start_at' => 'required|date|after:now',
                'duration' => 'required|integer|min:30|max:120',
            ]);

            // Convertir start_at a un formato estándar (Y-m-d H:i:s)
            $startAt = Carbon::parse($validated['start_at'])->format('Y-m-d H:i:s');
            $validated['start_at'] = $startAt;

            // Verificar si el horario está disponible
            $existingReservation = Reservation::where('pitch_id', $validated['pitch_id'])
                ->where('start_at', '<=', $validated['start_at'])
                ->whereRaw('DATE_ADD(start_at, INTERVAL duration MINUTE) > ?', [$validated['start_at']])
                ->whereNull('cancellation_date')
                ->first();

            if ($existingReservation) {
                $conflictStart = Carbon::parse($existingReservation->start_at)->format('d/m/Y H:i');
                $conflictEnd = Carbon::parse($existingReservation->start_at)
                    ->addMinutes($existingReservation->duration)
                    ->format('d/m/Y H:i');

                Log::warning('Intento de reserva fallido: horario ya reservado', [
                    'user_id' => $user->id,
                    'pitch_id' => $validated['pitch_id'],
                    'start_at' => $validated['start_at'],
                    'duration' => $validated['duration'],
                    'conflict_start' => $conflictStart,
                    'conflict_end' => $conflictEnd,
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'message' => 'El horario seleccionado ya está reservado.',
                    'details' => "El campo está ocupado desde $conflictStart hasta $conflictEnd. Por favor, elige otro horario o campo.",
                ], 400);
            }

            $pitch = Pitch::findOrFail($validated['pitch_id']);
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

            Log::info('Reserva creada con éxito', [
                'reservation_id' => $reservation->id,
                'user_id' => $user->id,
                'pitch_id' => $validated['pitch_id'],
                'start_at' => $validated['start_at'],
                'duration' => $validated['duration'],
                'ip' => $request->ip(),
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
                ],
            ], 201);
        } catch (ValidationException $e) {
            Log::warning('Error de validación al crear reserva', [
                'user_id' => $user->id ?? null,
                'errors' => $e->errors(),
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Datos de reserva inválidos.',
                'details' => $e->errors(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error inesperado al crear reserva', [
                'user_id' => $user->id ?? null,
                'message' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Error inesperado al crear la reserva.',
                'details' => 'Por favor, intenta de nuevo más tarde.',
            ], 500);
        }
    }
}
