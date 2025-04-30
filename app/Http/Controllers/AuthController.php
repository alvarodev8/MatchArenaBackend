<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            $user->currentAccessToken()->delete();

            Log::info('Usuario deslogueado con éxito', [
                'email' => $user->email,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Sesión cerrada con éxito',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al cerrar sesión', [
                'email' => $request->user()->email,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Error al cerrar sesión',
            ], 500);
        }
    }

    public function getUser(Request $request)
    {
        try {
            $user = $request->user();

            Log::info('Información del usuario obtenida', [
                'email' => $user->email,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Usuario obtenido con éxito',
                'user' => $user,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener usuario', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Error al obtener el usuario',
            ], 500);
        }
    }

    public function forgotPassword(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|string|email',
            ]);

            $status = Password::sendResetLink($validated);

            if ($status === Password::RESET_LINK_SENT) {
                Log::info('Enlace de recuperación enviado', [
                    'email' => $validated['email'],
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'message' => 'Enlace de recuperación enviado al correo',
                ], 200);
            }

            Log::warning('Error al enviar enlace de recuperación', [
                'email' => $validated['email'],
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'No se pudo enviar el enlace de recuperación',
            ], 400);
        } catch (ValidationException $e) {
            Log::warning('Error de validación en recuperación de contraseña', [
                'email' => $request->email,
                'errors' => $e->errors(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error inesperado en recuperación de contraseña', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Error al procesar la solicitud',
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $validated = $request->validate([
                'token' => 'required|string',
                'email' => 'required|string|email',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();

                    event(new PasswordReset($user));
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                Log::info('Contraseña restablecida con éxito', [
                    'email' => $validated['email'],
                    'ip' => $request->ip(),
                ]);
                return response()->json([
                    'message' => 'Contraseña restablecida con éxito',
                ], 200);
            }

            Log::warning('Error al restablecer la contraseña', [
                'email' => $validated['email'],
                'status' => $status,
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'No se pudo restablecer la contraseña',
            ], 400);
        } catch (ValidationException $e) {
            Log::warning('Error de validación en restablecimiento de contraseña', [
                'email' => $request->email,
                'errors' => $e->errors(),
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error inesperado en restablecimiento de contraseña', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Error al procesar la solicitud',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function validateResetToken(Request $request, $token)
    {
        try {
            $resetToken = DB::table('password_reset_tokens')
                ->where('token', $token)
                ->first();

            if (!$resetToken) {
                Log::warning('Token de restablecimiento inválido', [
                    'token' => $token,
                    'ip' => $request->ip(),
                ]);
                return response()->json([
                    'message' => 'Token inválido o expirado',
                ], 400);
            }

            $createdAt = \Carbon\Carbon::parse($resetToken->created_at);
            $expirationMinutes = config('auth.passwords.users.expire', 60);
            if ($createdAt->diffInMinutes(now()) > $expirationMinutes) {
                Log::warning('Token de restablecimiento expirado', [
                    'token' => $token,
                    'email' => $resetToken->email,
                    'ip' => $request->ip(),
                ]);
                return response()->json([
                    'message' => 'Token expirado',
                ], 400);
            }

            Log::info('Token de restablecimiento válido', [
                'token' => $token,
                'email' => $resetToken->email,
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'email' => $resetToken->email,
                'token' => $token,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al validar el token de restablecimiento', [
                'token' => $token,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Error al procesar la solicitud',
            ], 500);
        }
    }
}
