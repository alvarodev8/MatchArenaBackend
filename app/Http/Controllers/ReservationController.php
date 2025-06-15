<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponser;
use App\Models\Pitch;
use App\Models\Reservation;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Refund;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;

class ReservationController extends Controller
{
    use ApiResponser;

    protected $reservationService;

    public function __construct(ReservationService $reservationService)
    {
        $this->reservationService = $reservationService;
    }

    /**
     * Lista las reservas del jugador
     */
    public function index(Request $request)
    {
        try {
            $reservations = Reservation::where('player_id', auth()->id())
                ->with('pitch')
                ->get()
                ->map(function ($reservation) {
                    return [
                        'id' => $reservation->id,
                        'start_at' => $reservation->start_at,
                        'duration' => $reservation->duration,
                        'price' => $reservation->price,
                        'status' => $reservation->status,
                        'cancellation_reason' => $reservation->cancellation_reason,
                        'cancellation_date' => $reservation->cancellation_date,
                        'pitch' => [
                            'id' => $reservation->pitch->id,
                            'name' => $reservation->pitch->name,
                            'location' => $reservation->pitch->location,
                        ],
                    ];
                });

            Log::info('Reservas obtenidas por jugador', [
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
            ]);

            return $this->successResponse(['reservations' => $reservations], 'Reservas obtenidas con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'obtención de reservas');
        }
    }

    /**
     * Crea una nueva reserva para el jugador
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'pitch_id' => 'required|exists:pitches,id',
                'start_at' => 'required|date|after:now',
                'duration' => 'required|integer|min:60|max:120',
                'stripe_payment_intent_id' => 'required|string',
            ]);

            $startAt = Carbon::parse($validated['start_at']);
            $availability = $this->reservationService->checkAvailability(
                $validated['pitch_id'],
                $startAt,
                $validated['duration'],
                $request
            );

            if (!$availability['available']) {
                return $this->errorResponse(
                    'El horario seleccionado ya está reservado',
                    400,
                    ['details' => $availability['message'] ?? 'Horario no disponible']
                );
            } else if (isset($availability['message']) && !$availability['available']) {
                return $this->errorResponse('Error al verificar disponibilidad', 500, ['details' => $availability['message']]);
            }

            $pitch = Pitch::findOrFail($validated['pitch_id']);
            $price = $pitch->price * $validated['duration'] / 60 ?? 30.00;

            // Verificar el Payment Intent
            Stripe::setApiKey(config('services.stripe.secret'));
            $paymentIntent = PaymentIntent::retrieve($validated['stripe_payment_intent_id']);

            if ($paymentIntent->status !== 'succeeded') {
                return $this->errorResponse('El pago no se ha completado', 400);
            }

            $reservation = DB::transaction(function () use ($validated, $startAt, $pitch, $price) {
                return Reservation::create([
                    'player_id' => auth()->id(),
                    'pitch_id' => $validated['pitch_id'],
                    'start_at' => $startAt->format('Y-m-d H:i:s'),
                    'duration' => $validated['duration'],
                    'price' => $price,
                    'status' => 'confirmed',
                    'payment_status' => 'completed',
                    'payment_method' => 'card',
                    'stripe_payment_intent_id' => $validated['stripe_payment_intent_id'],
                ]);
            });

            Log::info('Reserva creada con éxito', [
                'reservation_id' => $reservation->id,
                'user_id' => auth()->id(),
                'pitch_id' => $validated['pitch_id'],
                'start_at' => $startAt->toDateTimeString(),
                'duration' => $validated['duration'],
                'stripe_payment_intent_id' => $validated['stripe_payment_intent_id'],
                'ip' => $request->ip(),
            ]);

            return $this->successResponse([
                'reservation' => [
                    'id' => $reservation->id,
                    'start_at' => $reservation->start_at,
                    'duration' => $reservation->duration,
                    'price' => $reservation->price,
                    'status' => $reservation->status,
                    'stripe_payment_intent_id' => $reservation->stripe_payment_intent_id,
                    'pitch' => [
                        'id' => $pitch->id,
                        'name' => $pitch->name,
                        'location' => $pitch->location,
                    ],
                ],
            ], 'Reserva creada con éxito', 201);
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'creación de reserva');
        }
    }

    /**
     * Verifica la disponibilidad de un horario.
     */
    public function checkAvailability(Request $request)
    {
        try {
            $validated = $request->validate([
                'pitch_id' => 'required|exists:pitches,id',
                'start_at' => 'required|date|after:now',
                'duration' => 'required|integer|min:60|max:120',
                'current_reservation_id' => 'nullable|exists:reservations,id',
            ]);

            $result = $this->reservationService->checkAvailability(
                $validated['pitch_id'],
                Carbon::parse($validated['start_at']),
                $validated['duration'],
                $request,
                $validated['current_reservation_id'] ?? null
            );

            return $this->successResponse(
                $result,
                $result['available'] ? 'Horario disponible' : 'Horario no disponible'
            );
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'verificación de disponibilidad');
        }
    }

    /**
     * Obtiene los horarios disponibles para un campo en una fecha.
     */
    public function getAvailableTimes(Request $request)
    {
        try {
            $validated = $request->validate([
                'pitch_id' => 'required|exists:pitches,id',
                'date' => 'required|date',
            ]);

            $availableTimes = $this->reservationService->getAvailableTimes(
                $validated['pitch_id'],
                Carbon::parse($validated['date'])->startOfDay(),
                $request
            );

            return $this->successResponse(['availableTimes' => $availableTimes], 'Horarios disponibles obtenidos con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'obtención de horarios disponibles');
        }
    }

    /**
     * Obtiene las fechas disponibles para un campo en un rango.
     */
    public function getAvailableDates(Request $request)
    {
        try {
            $validated = $request->validate([
                'pitch_id' => 'required|exists:pitches,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $dates = $this->reservationService->getAvailableDates(
                $validated['pitch_id'],
                Carbon::parse($validated['start_date'])->startOfDay(),
                Carbon::parse($validated['end_date'])->endOfDay(),
                $request
            );

            return $this->successResponse(['dates' => $dates], 'Fechas disponibles obtenidas con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'obtención de fechas disponibles');
        }
    }

    /**
     * Crea un intento de pago con Stripe para una reserva.
     */
    public function createPaymentIntent(Request $request)
    {
        Log::info('Solicitud de creación de Payment Intent recibida', $request->all());

        try {
            $validated = $request->validate([
                'pitch_id' => 'required|exists:pitches,id',
                'start_at' => 'required|date|after:now',
                'duration' => 'required|integer|min:60|max:120',
            ]);

            $pitch = Pitch::findOrFail($validated['pitch_id']);
            $price = $pitch->price * $validated['duration'] / 60 ?? 30.00;

            // Configurar Stripe
            Stripe::setApiKey(config('services.stripe.secret'));

            // Crear Payment Intent
            $paymentIntent = PaymentIntent::create([
                'amount' => $price * 100, // Stripe usa centavos
                'currency' => 'eur',
                'payment_method_types' => ['card'],
                'metadata' => [
                    'pitch_id' => $validated['pitch_id'],
                    'player_id' => auth()->id(),
                    'start_at' => $validated['start_at'],
                    'duration' => $validated['duration'],
                ],
            ]);

            Log::info('Payment Intent creado con éxito', [
                'payment_intent_id' => $paymentIntent->id,
                'user_id' => auth()->id(),
                'pitch_id' => $validated['pitch_id'],
                'amount' => $price,
                'ip' => $request->ip(),
            ]);

            return $this->successResponse([
                'client_secret' => $paymentIntent->client_secret,
            ], 'Payment Intent creado con éxito');
        } catch (\Exception $e) {
            Log::error('Error al crear Payment Intent', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);
            return $this->errorResponse('Error al crear el intento de pago', 500);
        }
    }

    public function modifyReservation(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'start_at' => 'required|date|after:now',
                'duration' => 'required|integer|min:60|max:120',
            ]);

            $reservation = Reservation::where('player_id', auth()->id())->findOrFail($id);

            if (!is_null($reservation->cancellation_date)) {
                return $this->errorResponse('La reserva ya está cancelada', 400);
            }

            // Calcular nuevo precio
            $pitch = Pitch::findOrFail($reservation->pitch_id);
            $newPrice = $pitch->price * $validated['duration'] / 60 ?? 30.00;

            $newStartAt = Carbon::parse($validated['start_at']);

            // Verificar disponibilidad del nuevo horario (opcional, según tu preferencia)
            $availabilityNew = $this->reservationService->checkAvailability(
                $reservation->pitch_id,
                $newStartAt,
                $validated['duration'],
                $request,
                $id // Excluir la reserva actual
            );

            if (!$availabilityNew['available']) {
                return $this->errorResponse('El nuevo horario no está disponible', 400);
            }

            DB::transaction(function () use ($reservation, $validated, $newStartAt, $newPrice) {
                $reservation->update([
                    'start_at' => $newStartAt->format('Y-m-d H:i:s'),
                    'duration' => $validated['duration'],
                    'price' => $newPrice,
                ]);
            });

            Log::info('Reserva modificada con éxito', [
                'reservation_id' => $reservation->id,
                'user_id' => auth()->id(),
                'new_start_at' => $newStartAt->toDateTimeString(),
                'new_duration' => $validated['duration'],
                'new_price' => $newPrice,
                'ip' => $request->ip(),
            ]);

            return $this->successResponse([
                'reservation' => [
                    'id' => $reservation->id,
                    'start_at' => $reservation->start_at,
                    'duration' => $reservation->duration,
                    'price' => $reservation->price,
                    'status' => $reservation->status,
                ],
            ], 'Reserva modificada con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'modificación de reserva');
        }
    }

    public function cancelReservation(Request $request, $id)
    {
        try {
            $reservation = Reservation::where('player_id', auth()->id())->findOrFail($id);

            if (!is_null($reservation->cancellation_date)) {
                return $this->errorResponse('La reserva ya está cancelada', 400);
            }

            $now = Carbon::now();
            if ($now->greaterThanOrEqualTo(Carbon::parse($reservation->start_at))) {
                return $this->errorResponse('No se puede cancelar una reserva pasada', 400);
            }

            DB::transaction(function () use ($reservation, $now) {
                if (!is_null($reservation->stripe_payment_intent_id)) {
                    Stripe::setApiKey(config('services.stripe.secret'));
                    try {
                        Refund::create([
                            'payment_intent' => $reservation->stripe_payment_intent_id,
                            'amount' => (int) ($reservation->price * 100),
                            'reason' => 'requested_by_customer',
                        ]);
                        $reservation->update([
                            'payment_status' => 'refunded',
                        ]);
                    } catch (ApiErrorException $e) {
                        Log::error('Error al reembolsar reserva', [
                            'reservation_id' => $reservation->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $reservation->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => 'Cancelada por el jugador',
                    'cancellation_date' => $now,
                ]);
            });

            Log::info('Reserva cancelada por jugador', [
                'reservation_id' => $reservation->id,
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
            ]);

            return $this->successResponse([], 'Reserva cancelada con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'cancelación de reserva');
        }
    }
}
