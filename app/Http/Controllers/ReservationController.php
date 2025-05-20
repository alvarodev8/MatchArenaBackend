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
                ->where('start_at', '<', Carbon::parse($startAt)->addMinutes($validated['duration']))
                ->whereRaw('DATE_ADD(start_at, INTERVAL duration MINUTE) > ?', [$startAt])
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

    public function checkAvailability(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'pitch_id' => 'required|exists:pitches,id',
            'start_at' => 'required|date|after:now',
            'duration' => 'required|integer|min:30|max:120',
        ]);

        $startAt = Carbon::parse($validated['start_at'])->format('Y-m-d H:i:s');
        $endAt = Carbon::parse($startAt)->addMinutes($validated['duration']);

        $existingReservation = Reservation::where('pitch_id', $validated['pitch_id'])
            ->where(function ($query) use ($startAt, $endAt) {
                $query->where('start_at', '<', $endAt)
                      ->whereRaw('DATE_ADD(start_at, INTERVAL duration MINUTE) > ?', [$startAt]);
            })
            ->whereNull('cancellation_date')
            ->first();

        if ($existingReservation) {
            $conflictStart = Carbon::parse($existingReservation->start_at)->format('d/m/Y H:i');
            $conflictEnd = Carbon::parse($existingReservation->start_at)
                ->addMinutes($existingReservation->duration)
                ->format('d/m/Y H:i');
            return response()->json([
                'available' => false,
                'message' => "El campo está ocupado desde $conflictStart hasta $conflictEnd."
            ], 200);
        }

        return response()->json(['available' => true], 200);
    }

    public function getAvailableTimes(Request $request)
    {
        $validated = $request->validate([
            'pitch_id' => 'required|exists:pitches,id',
            'date' => 'required|date',
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();
        $availableTimes = [];
        $allTimes = [];
        for ($hour = 8; $hour < 22; $hour++) {
            $allTimes[] = sprintf("%02d:00", $hour);
            $allTimes[] = sprintf("%02d:30", $hour);
        }

        $now = Carbon::now();
        if ($date->isSameDay($now)) {
            $currentHour = $now->hour;
            $currentMinute = $now->minute;
            $nextHalfHour = ceil($currentMinute / 30) * 30;
            $startHour = $currentHour + ($nextHalfHour === 60 ? 1 : ($nextHalfHour === 0 ? 0 : 1));
            $allTimes = array_filter($allTimes, function ($time) use ($startHour) {
                $hour = (int)explode(':', $time)[0];
                return $hour >= $startHour;
            });
        }

        foreach ($allTimes as $time) {
            $startAt = $date->copy()->setTimeFromTimeString($time);
            $endAt = $startAt->copy()->addMinutes(30); // intervalos de 30 minutos para verificar
            $existingReservation = Reservation::where('pitch_id', $validated['pitch_id'])
                ->where(function ($query) use ($startAt, $endAt) {
                    $query->where('start_at', '<', $endAt)
                          ->whereRaw('DATE_ADD(start_at, INTERVAL duration MINUTE) > ?', [$startAt]);
                })
                ->whereNull('cancellation_date')
                ->first();

            if (!$existingReservation) {
                $availableTimes[] = $time;
            }
        }

        return response()->json(['availableTimes' => $availableTimes], 200);
    }

    public function getAvailableDates(Request $request)
    {
        $validated = $request->validate([
            'pitch_id' => 'required|exists:pitches,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();
        $now = Carbon::now();
        $maxEndDate = $now->copy()->addDays(15)->endOfDay();

        if ($endDate > $maxEndDate) {
            $endDate = $maxEndDate;
        }

        $dates = [];
        for ($date = $startDate; $date <= $endDate; $date->addDay()) {
            $availableTimes = [];
            $allTimes = [];
            for ($hour = 8; $hour < 22; $hour++) {
                $allTimes[] = sprintf("%02d:00", $hour);
                $allTimes[] = sprintf("%02d:30", $hour);
            }

            foreach ($allTimes as $time) {
                $startAt = $date->copy()->setTimeFromTimeString($time);
                $endAt = $startAt->copy()->addMinutes(30); // intervalos de 30 minutos para verificar
                $existingReservation = Reservation::where('pitch_id', $validated['pitch_id'])
                    ->where(function ($query) use ($startAt, $endAt) {
                        $query->where('start_at', '<', $endAt)
                              ->whereRaw('DATE_ADD(start_at, INTERVAL duration MINUTE) > ?', [$startAt]);
                    })
                    ->whereNull('cancellation_date')
                    ->first();

                if (!$existingReservation) {
                    $availableTimes[] = $time;
                }
            }

            $dates[] = [
                'date' => $date->format('Y-m-d'),
                'available' => !empty($availableTimes)
            ];
        }

        return response()->json($dates, 200);
    }
}
