<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponser;
use Illuminate\Http\Request;
use App\Models\User;

class AdminController extends Controller
{
    use ApiResponser;

    public function users(Request $request)
    {
        $users = User::all()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ];
        });

        return $this->successResponse(['users' => $users], 'Usuarios obtenidos con éxito');
    }
}
