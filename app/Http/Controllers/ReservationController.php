<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponser;
use App\Models\Pitch;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ReservationController extends Controller
{
    use ApiResponser;

    /**
     * Lista las reservas del jugador
     */
    public function index(Request $request)
    {
        try {
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

            return $this->successResponse(['reservations' => $reservations], 'Reservas obtenidas con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'obtención de reservas');
        }
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

                return $this->errorResponse(
                    'El horario seleccionado ya está reservado.',
                    400,
                    ['details' => "El campo está ocupado desde $conflictStart hasta $conflictEnd. Por favor, elige otro horario o campo."]
                );
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

            return $this->successResponse([
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
            ], 'Reserva creada con éxito', 201);
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'creación de reserva');
        }
    }

    public function checkAvailability(Request $request)
    {
        try {
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
                return $this->successResponse([
                    'available' => false,
                    'message' => "El campo está ocupado desde $conflictStart hasta $conflictEnd."
                ], 'Horario no disponible');
            }

            return $this->successResponse(['available' => true], 'Horario disponible');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'verificación de disponibilidad');
        }
    }

    public function getAvailableTimes(Request $request)
    {
        try {
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

            return $this->successResponse(['availableTimes' => $availableTimes], 'Horarios disponibles obtenidos con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'obtención de horarios disponibles');
        }
    }

    public function getAvailableDates(Request $request)
    {
        try {
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

            return $this->successResponse(['dates' => $dates], 'Fechas disponibles obtenidas con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'obtención de fechas disponibles');
        }
    }
}
