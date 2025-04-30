<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Pitch;
use App\Models\Fixture;

class PlayerController extends Controller
{
    public function profile(Request $request)
    {
        $user = Auth::user();
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ]);
    }

    public function fixtures(Request $request)
    {
        $user = Auth::user();
        $fixtures = Fixture::where('player_id', $user->id)
            ->with('pitch')
            ->get()
            ->map(function ($fixture) {
                return [
                    'id' => $fixture->id,
                    'opponent' => $fixture->opponent,
                    'date' => $fixture->date,
                    'pitch' => [
                        'id' => $fixture->pitch->id,
                        'name' => $fixture->pitch->name,
                        'location' => $fixture->pitch->location,
                    ],
                ];
            });

        return response()->json($fixtures);
    }
}
