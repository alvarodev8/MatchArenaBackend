<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Pitch;

class PitchController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if ($user->role === 'player') {
            // Para jugadores: devolver todos los pitches disponibles
            $pitches = Pitch::all()->map(function ($pitch) {
                return [
                    'id' => $pitch->id,
                    'name' => $pitch->name,
                    'location' => $pitch->location,
                ];
            });
        } elseif ($user->role === 'establishment') {
            // Para establecimientos: devolver solo los pitches que gestionan
            $pitches = Pitch::where('establishment_id', $user->id)
                ->get()
                ->map(function ($pitch) {
                    return [
                        'id' => $pitch->id,
                        'name' => $pitch->name,
                        'location' => $pitch->location,
                    ];
                });
        } else {
            // Si el rol no es player ni establishment, denegar acceso
            return response()->json(['message' => 'Acceso denegado'], 403);
        }

        return response()->json($pitches);
    }
}
