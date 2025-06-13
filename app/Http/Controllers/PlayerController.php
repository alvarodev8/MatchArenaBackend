<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlayerController extends Controller
{
    use ApiResponser;

    protected $reservationController;
    protected $pitchController;

    public function __construct(
        ReservationController $reservationController,
        PitchController $pitchController
    ) {
        $this->reservationController = $reservationController;
        $this->pitchController = $pitchController;
    }

    /**
     * Muestra el perfil del jugador.
     */
    public function profile(Request $request)
    {
        $user = Auth::user();
        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ], 'Perfil obtenido con éxito');
    }

    /**
     * Lista las reservas del jugador
     */
    public function reservations(Request $request)
    {
        return $this->reservationController->index($request);
    }

    /**
     * Crea una nueva reserva
     */
    public function createReservation(Request $request)
    {
        return $this->reservationController->store($request);
    }

    /**
     * Lista los campos disponibles
     */
    public function getPitches(Request $request)
    {
        return $this->pitchController->availablePitches($request);
    }
}
