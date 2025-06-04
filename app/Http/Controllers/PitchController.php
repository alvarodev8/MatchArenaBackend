<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Pitch;

class PitchController extends Controller
{
    use ApiResponser;

    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $query = Pitch::query();

            // Filtrar según el rol del usuario
            if ($user->role === 'establishment') {
                $query->where('establishment_id', $user->id);
            } elseif ($user->role !== 'player' && $user->role !== 'establishment') {
                return $this->errorResponse('Acceso denegado', 403);
            }

            $pitches = $query->with('establishment')->get()->map(function ($pitch) {
                return [
                    'id' => $pitch->id,
                    'name' => $pitch->name,
                    'location' => $pitch->location,
                    'establishment' => [
                        'id' => $pitch->establishment->id,
                        'name' => $pitch->establishment->name,
                    ],
                ];
            });

            return $this->successResponse(['pitches' => $pitches], 'Campos obtenidos con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'obtención de campos');
        }
    }
}
