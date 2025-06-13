<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\PitchController;

class EstablishmentController extends Controller
{
    use ApiResponser;

    protected $pitchController;

    public function __construct(PitchController $pitchController)
    {
        $this->pitchController = $pitchController;
    }

    /**
     * Lista todos los pitches del establecimiento (incluye soft deleted).
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
}
