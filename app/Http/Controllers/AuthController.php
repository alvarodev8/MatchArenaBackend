<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|unique:users|max:255',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'required|in:player,establishment',
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            Log::info('Usuario registrado con éxito', [
                'email' => $user->email,
                'role' => $user->role,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Usuario registrado con éxito',
                'user' => $user,
                'token' => $token,
            ], 201);
        } catch (ValidationException $e) {
            Log::warning('Error de validación en registro', [
                'email' => $request->email,
                'errors' => $e->errors(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error inesperado en registro', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Error al registrar el usuario',
            ], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);

            if (!Auth::attempt($validated)) {
                Log::warning('Intento de login fallido', [
                    'email' => $validated['email'],
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'message' => 'Credenciales inválidas',
                ], 401);
            }

            $user = Auth::user();

            $token = $user->createToken('auth_token')->plainTextToken;

            Log::info('Usuario logueado con éxito', [
                'email' => $user->email,
                'role' => $user->role,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Inicio de sesión exitoso',
                'user' => $user,
                'token' => $token,
            ], 200);
        } catch (ValidationException $e) {
            Log::warning('Error de validación en login', [
                'email' => $request->email,
                'errors' => $e->errors(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error inesperado en login', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Error al iniciar sesión',
            ], 500);
        }
    }
}
