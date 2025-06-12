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
                        'cancelled_at' => $reservation->cancellation_date,
                        'is_cancelled' => $reservation->cancellation_date !== null,
                        'pitch' => [
                            'id' => $reservation->pitch->id,
                            'name' => $reservation->pitch->name,
                            'location' => $reservation->pitch->location,
                        ],
                    ];
                });

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
                'stripe_payment_intent_id' => 'required|string', // Nuevo campo
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
            $price = $pitch->price ?? 30.00;

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
            ]);

            $result = $this->reservationService->checkAvailability(
                $validated['pitch_id'],
                Carbon::parse($validated['start_at']),
                $validated['duration'],
                $request
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

    public function createPaymentIntent(Request $request)
    {
        Log::info('Solicitud recibida', $request->all());

        try {
            $validated = $request->validate([
                'pitch_id' => 'required|exists:pitches,id',
                'start_at' => 'required|date|after:now',
                'duration' => 'required|integer|min:60|max:120',
            ]);

            $pitch = Pitch::findOrFail($validated['pitch_id']);
            $price = $pitch->price ?? 30.00;

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

            Log::info('Payment Intent creado', [
                'payment_intent_id' => $paymentIntent->id,
                'user_id' => auth()->id(),
                'pitch_id' => $validated['pitch_id'],
                'amount' => $price,
            ]);

            return $this->successResponse([
                'client_secret' => $paymentIntent->client_secret,
            ], 'Payment Intent creado con éxito');
        } catch (\Exception $e) {
            Log::error('Error al crear Payment Intent', ['error' => $e->getMessage()]);
            return $this->errorResponse('Error al crear el intento de pago', 500);
        }
    }
}
