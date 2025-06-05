<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponser;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    use ApiResponser;

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

            return $this->successResponse([
                'user' => $user,
                'token' => $token,
            ], 'Usuario registrado con éxito', 201);
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'registro');
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

                return $this->errorResponse('Credenciales inválidas', 401);
            }

            $user = Auth::user();

            $token = $user->createToken('auth_token')->plainTextToken;

            Log::info('Usuario logueado con éxito', [
                'email' => $user->email,
                'role' => $user->role,
                'ip' => $request->ip(),
            ]);

            return $this->successResponse([
                'user' => $user,
                'token' => $token,
            ], 'Inicio de sesión exitoso');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'login');
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

            return $this->successResponse([], 'Sesión cerrada con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'logout');
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

            return $this->successResponse([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
            ], 'Usuario obtenido con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'obtención de usuario');
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

                return $this->successResponse([], 'Enlace de recuperación enviado al correo');
            }

            Log::warning('Error al enviar enlace de recuperación', [
                'email' => $validated['email'],
                'ip' => $request->ip(),
            ]);

            return $this->errorResponse('No se pudo enviar el enlace de recuperación', 400);
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'recuperación de contraseña');
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
                return $this->successResponse([], 'Contraseña restablecida con éxito');
            }

            Log::warning('Error al restablecer la contraseña', [
                'email' => $validated['email'],
                'status' => $status,
                'ip' => $request->ip(),
            ]);
            return $this->errorResponse('No se pudo restablecer la contraseña', 400);
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'restablecimiento de contraseña');
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
                return $this->errorResponse('Token inválido o expirado', 400);
            }

            $createdAt = \Carbon\Carbon::parse($resetToken->created_at);
            $expirationMinutes = config('auth.passwords.users.expire', 60);
            if ($createdAt->diffInMinutes(now()) > $expirationMinutes) {
                Log::warning('Token de restablecimiento expirado', [
                    'token' => $token,
                    'email' => $resetToken->email,
                    'ip' => $request->ip(),
                ]);
                return $this->errorResponse('Token expirado', 400);
            }

            Log::info('Token de restablecimiento válido', [
                'token' => $token,
                'email' => $resetToken->email,
                'ip' => $request->ip(),
            ]);
            return $this->successResponse([
                'email' => $resetToken->email,
                'token' => $token,
            ], 'Token válido');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'validación de token de restablecimiento');
        }
    }
}
