<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponser;
use App\Models\Pitch;
use App\Models\Reservation;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReservationController extends Controller
{
    use ApiResponser;

    protected $reservationService;

    public function __construct(ReservationService $reservationService)
    {
        $this->reservationService = $reservationService;
    }

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
        try {
            $validated = $request->validate([
                'pitch_id' => 'required|exists:pitches,id',
                'start_at' => 'required|date|after:now',
                'duration' => 'required|integer|min:60|max:120',
            ]);

            $startAt = Carbon::parse($validated['start_at']);
            $availability = $this->reservationService->checkAvailability(
                $validated['pitch_id'],
                $startAt,
                $validated['duration'],
                $request
            );

            if (!$availability['available']) {
                return $this->errorResponse(
                    'El horario seleccionado ya está reservado',
                    400,
                    ['details' => $availability['message'] ?? 'Horario no disponible']
                );
            } else if (isset($availability['message']) && !$availability['available']) {
                return $this->errorResponse('Error al verificar disponibilidad', 500, ['details' => $availability['message']]);
            }

            $pitch = Pitch::findOrFail($validated['pitch_id']);
            $price = $pitch->price ?? 30.00;

            $reservation = Reservation::create([
                'player_id' => auth()->id(),
                'pitch_id' => $validated['pitch_id'],
                'start_at' => $startAt->format('Y-m-d H:i:s'),
                'duration' => $validated['duration'],
                'price' => $price,
                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => null,
            ]);

            Log::info('Reserva creada con éxito', [
                'reservation_id' => $reservation->id,
                'user_id' => auth()->id(),
                'pitch_id' => $validated['pitch_id'],
                'start_at' => $startAt->toDateTimeString(),
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

    /**
     * Verifica la disponibilidad de un horario.
     */
    public function checkAvailability(Request $request)
    {
        try {
            $validated = $request->validate([
                'pitch_id' => 'required|exists:pitches,id',
                'start_at' => 'required|date|after:now',
                'duration' => 'required|integer|min:60|max:120',
            ]);

            $result = $this->reservationService->checkAvailability(
                $validated['pitch_id'],
                Carbon::parse($validated['start_at']),
                $validated['duration'],
                $request
            );

            return $this->successResponse(
                $result,
                $result['available'] ? 'Horario disponible' : 'Horario no disponible'
            );
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'verificación de disponibilidad');
        }
    }

    /**
     * Obtiene los horarios disponibles para un campo en una fecha.
     */
    public function getAvailableTimes(Request $request)
    {
        try {
            $validated = $request->validate([
                'pitch_id' => 'required|exists:pitches,id',
                'date' => 'required|date',
            ]);

            $availableTimes = $this->reservationService->getAvailableTimes(
                $validated['pitch_id'],
                Carbon::parse($validated['date'])->startOfDay(),
                $request
            );

            return $this->successResponse(['availableTimes' => $availableTimes], 'Horarios disponibles obtenidos con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'obtención de horarios disponibles');
        }
    }

    /**
     * Obtiene las fechas disponibles para un campo en un rango.
     */
    public function getAvailableDates(Request $request)
    {
        try {
            $validated = $request->validate([
                'pitch_id' => 'required|exists:pitches,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $dates = $this->reservationService->getAvailableDates(
                $validated['pitch_id'],
                Carbon::parse($validated['start_date'])->startOfDay(),
                Carbon::parse($validated['end_date'])->endOfDay(),
                $request
            );

            return $this->successResponse(['dates' => $dates], 'Fechas disponibles obtenidas con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'obtención de fechas disponibles');
        }
    }
}
