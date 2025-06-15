<?php

namespace App\Observers;

use App\Models\Pitch;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Refund;
use Stripe\Stripe;

class PitchObserver
{
    /**
     * Handle the Pitch "created" event.
     *
     * @param  \App\Models\Pitch  $pitch
     * @return void
     */
    public function created(Pitch $pitch)
    {
        //
    }

    /**
     * Handle the Pitch "updated" event.
     *
     * @param  \App\Models\Pitch  $pitch
     * @return void
     */
    public function updated(Pitch $pitch)
    {
        //
    }

    /**
     * Handle the Pitch "deleted" event.
     *
     * @param  \App\Models\Pitch  $pitch
     * @return void
     */
    public function deleted(Pitch $pitch)
    {
        //
    }

    /**
     * Maneja el evento "deleting" (soft delete) del modelo Pitch.
     */
    public function deleting(Pitch $pitch)
    {
        $now = Carbon::now();

        // Obtener solo las reservas futuras
        $futureReservations = $pitch->reservations()
            ->where('start_at', '>', $now)
            ->whereNotNull('stripe_payment_intent_id')
            ->get();

        DB::transaction(function () use ($pitch, $futureReservations, $now) {
            foreach ($futureReservations as $reservation) {
                try {
                    Stripe::setApiKey(config('services.stripe.secret'));

                    // Realizar reembolso
                    Refund::create([
                        'payment_intent' => $reservation->stripe_payment_intent_id,
                        'amount' => (int) ($reservation->price * 100), // Convertir a centavos
                        'reason' => 'requested_by_customer',
                    ]);

                    // Actualizar reserva
                    $reservation->update([
                        'status' => 'cancelled',
                        'cancellation_reason' => 'Campo eliminado por el establecimiento',
                        'cancellation_date' => $now,
                        'payment_status' => 'refunded',
                    ]);
                } catch (ApiErrorException $e) {
                    Log::error('Error al reembolsar reserva al eliminar campo', [
                        'reservation_id' => $reservation->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Marcar el campo como eliminado
            $pitch->update(['deleted_at' => $now]);
        });
    }

    /**
     * Handle the Pitch "restored" event.
     *
     * @param  \App\Models\Pitch  $pitch
     * @return void
     */
    public function restored(Pitch $pitch)
    {
        //
    }

    /**
     * Handle the Pitch "force deleted" event.
     *
     * @param  \App\Models\Pitch  $pitch
     * @return void
     */
    public function forceDeleted(Pitch $pitch)
    {
        //
    }
}
