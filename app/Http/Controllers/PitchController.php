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

        $query = Pitch::query();

        // Filtrar según el rol del usuario
        if ($user->role === 'establishment') {
            $query->where('establishment_id', $user->id);
        } elseif ($user->role !== 'player' && $user->role !== 'establishment') {
            return response()->json(['message' => 'Acceso denegado'], 403);
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

        return response()->json($pitches);
    }
}
