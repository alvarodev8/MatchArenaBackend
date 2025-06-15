<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Refund;
use Stripe\Stripe;

class AdminController extends Controller
{
    use ApiResponser;

    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Lista todos los usuarios
     */
    public function users(Request $request)
    {
        try {
            $users = User::withTrashed()->get()->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_active' => is_null($user->deleted_at),
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'deleted_at' => $user->deleted_at,
                ];
            });

            Log::info('Lista de usuarios obtenida por administrador', [
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
            ]);

            return $this->successResponse(['users' => $users], 'Usuarios obtenidos con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'obtención de usuarios');
        }
    }

    /**
     * Muestra un usuario específico
     */
    public function showUser(Request $request, $id)
    {
        try {
            $user = User::withTrashed()->findOrFail($id);

            $response = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => is_null($user->deleted_at),
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];

            Log::info('Usuario obtenido por administrador', [
                'user_id' => auth()->id(),
                'target_user_id' => $id,
                'ip' => $request->ip(),
            ]);

            return $this->successResponse(['user' => $response], 'Usuario obtenido con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'obtención de usuario');
        }
    }

    /**
     * Crea un nuevo usuario
     */
    public function createUser(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'required|in:admin,player,establishment',
            ]);

            $user = DB::transaction(function () use ($validated) {
                return User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'role' => $validated['role'],
                ]);
            });

            Log::info('Usuario creado por administrador', [
                'user_id' => auth()->id(),
                'new_user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            return $this->successResponse(['user' => $user], 'Usuario creado con éxito', 201);
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'creación de usuario');
        }
    }

    /**
     * Actualiza un usuario existente
     */
    public function updateUser(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $id,
                'role' => 'sometimes|in:admin,player,establishment',
            ]);

            $user = User::withTrashed()->findOrFail($id);

            DB::transaction(function () use ($user, $validated) {
                $user->update($validated);
            });

            Log::info('Usuario actualizado por administrador', [
                'user_id' => auth()->id(),
                'target_user_id' => $id,
                'ip' => $request->ip(),
            ]);

            return $this->successResponse(['user' => $user], 'Usuario actualizado con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'actualización de usuario');
        }
    }

    /**
     * Elimina un usuario (soft delete)
     */
    public function deleteUser(Request $request, $id)
    {
        try {
            $user = User::withTrashed()->findOrFail($id);

            DB::transaction(function () use ($user) {
                $user->delete();
            });

            Log::info('Usuario eliminado por administrador', [
                'user_id' => auth()->id(),
                'target_user_id' => $id,
                'ip' => $request->ip(),
            ]);

            return $this->successResponse([], 'Usuario eliminado con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'eliminación de usuario');
        }
    }

    /**
     * Obtiene las reservas de un usuario
     */
    public function getUserReservations(Request $request, $id)
    {
        try {
            $user = User::withTrashed()->findOrFail($id);
            $reservations = Reservation::where('player_id', $user->id)
                ->with('pitch')
                ->get()
                ->map(function ($reservation) {
                    return [
                        'id' => $reservation->id,
                        'start_at' => $reservation->start_at,
                        'duration' => $reservation->duration,
                        'price' => $reservation->price,
                        'status' => $reservation->status,
                        'payment_status' => $reservation->payment_status,
                        'pitch' => [
                            'id' => $reservation->pitch->id,
                            'name' => $reservation->pitch->name,
                            'location' => $reservation->pitch->location,
                        ],
                    ];
                });

            Log::info('Reservas de usuario obtenidas por administrador', [
                'user_id' => auth()->id(),
                'target_user_id' => $id,
                'ip' => $request->ip(),
            ]);

            return $this->successResponse(['reservations' => $reservations], 'Reservas obtenidas con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'obtención de reservas de usuario');
        }
    }

    /**
     * Cancela una reserva de un usuario por parte de un administrador con reembolso
     */
    public function cancelReservation(Request $request, $userId, $reservationId)
    {
        try {
            $validated = $request->validate([
                'cancellation_reason' => 'required|string|max:1000',
            ]);

            $user = User::withTrashed()->findOrFail($userId);
            $reservation = Reservation::where('player_id', $user->id)
                ->findOrFail($reservationId);

            if (!is_null($reservation->deleted_at)) {
                return $this->errorResponse('La reserva ya está eliminada', 400);
            }

            if ($reservation->status === 'cancelled') {
                return $this->errorResponse('La reserva ya está cancelada', 400);
            }

            if (is_null($reservation->stripe_payment_intent_id)) {
                return $this->errorResponse('No hay información de pago para reembolsar', 400);
            }

            DB::transaction(function () use ($reservation, $validated) {
                try {
                    Refund::create([
                        'payment_intent' => $reservation->stripe_payment_intent_id,
                        'amount' => (int) ($reservation->price * 100), // Convertir a centavos
                        'reason' => 'requested_by_customer',
                    ]);
                } catch (ApiErrorException $e) {
                    throw new \Exception('Error al procesar el reembolso: ' . $e->getMessage());
                }

                $reservation->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => $validated['cancellation_reason'],
                    'cancellation_date' => Carbon::now(),
                    'payment_status' => 'refunded',
                ]);
            });

            Log::info('Reserva cancelada y reembolsada por administrador', [
                'user_id' => auth()->id(),
                'target_user_id' => $userId,
                'reservation_id' => $reservationId,
                'reason' => $validated['cancellation_reason'],
                'stripe_payment_intent_id' => $reservation->stripe_payment_intent_id,
                'ip' => $request->ip(),
            ]);

            return $this->successResponse([], 'Reserva cancelada y reembolsada con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'cancelación y reembolso de reserva');
        }
    }
}
