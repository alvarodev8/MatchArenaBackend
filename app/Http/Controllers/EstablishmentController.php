<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Pitch;

class EstablishmentController extends Controller
{
    public function pitches(Request $request)
    {
        $user = Auth::user();
        $pitches = Pitch::where('establishment_id', $user->id)
            ->get()
            ->map(function ($pitch) {
                return [
                    'id' => $pitch->id,
                    'name' => $pitch->name,
                    'location' => $pitch->location,
                ];
            });

        return response()->json($pitches);
    }
}
