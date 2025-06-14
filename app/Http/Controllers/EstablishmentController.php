<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\PitchController;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EstablishmentController extends Controller
{
    use ApiResponser;

    protected $pitchController;

    public function __construct(PitchController $pitchController)
    {
        $this->pitchController = $pitchController;
    }

    /**
     * Lista todos los pitches del establecimiento.
     */
    public function getPitches(Request $request)
    {
        return $this->pitchController->index($request);
    }

    /**
     * Muestra un pitch específico del establecimiento.
     */
    public function showPitch(Request $request, $id)
    {
        return $this->pitchController->show($request, $id);
    }

    /**
     * Crea un nuevo pitch para el establecimiento.
     */
    public function createPitch(Request $request)
    {
        return $this->pitchController->store($request);
    }

    /**
     * Actualiza un pitch del establecimiento.
     */
    public function updatePitch(Request $request, $id)
    {
        return $this->pitchController->update($request, $id);
    }

    /**
     * Marca un pitch como eliminado (soft delete).
     */
    public function deletePitch(Request $request, $id)
    {
        return $this->pitchController->destroy($request, $id);
    }

    /**
     * Lista todas las reservas de los campos del establecimiento.
     */
    public function getReservations(Request $request)
    {
        try {
            $reservations = Reservation::whereHas('pitch', function ($query) {
                $query->where('establishment_id', Auth::id());
            })
                ->with(['pitch', 'player'])
                ->get()
                ->map(function ($reservation) {
                    return [
                        'id' => $reservation->id,
                        'start_at' => $reservation->start_at,
                        'duration' => $reservation->duration,
                        'price' => $reservation->price,
                        'status' => $reservation->status,
                        'cancellation_reason' => $reservation->cancellation_reason,
                        'cancellation_date' => $reservation->cancellation_date,
                        'player' => [
                            'id' => $reservation->player->id,
                            'name' => $reservation->player->name,
                            'email' => $reservation->player->email,
                        ],
                        'pitch' => [
                            'id' => $reservation->pitch->id,
                            'name' => $reservation->pitch->name,
                            'location' => $reservation->pitch->location,
                        ],
                    ];
                });

            Log::info('Reservas obtenidas por establecimiento', [
                'user_id' => Auth::id(),
                'ip' => $request->ip(),
            ]);

            return $this->successResponse(['reservations' => $reservations], 'Reservas obtenidas con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'obtención de reservas');
        }
    }

    /**
     * Cancela una reserva específica del establecimiento.
     */
    public function cancelReservation(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'cancellation_reason' => 'required|string|max:1000',
            ]);

            $reservation = Reservation::whereHas('pitch', function ($query) {
                $query->where('establishment_id', Auth::id());
            })->findOrFail($id);

            if ($reservation->status === 'cancelled') {
                return $this->errorResponse('La reserva ya está cancelada', 400);
            }

            DB::transaction(function () use ($reservation, $validated) {
                $reservation->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => $validated['cancellation_reason'],
                    'cancellation_date' => Carbon::now(),
                ]);
            });

            Log::info('Reserva cancelada por establecimiento', [
                'reservation_id' => $reservation->id,
                'user_id' => Auth::id(),
                'reason' => $validated['cancellation_reason'],
                'ip' => $request->ip(),
            ]);

            return $this->successResponse([], 'Reserva cancelada con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'cancelación de reserva');
        }
    }
}
