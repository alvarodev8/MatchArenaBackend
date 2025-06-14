<?php

namespace App\Observers;

use App\Models\Pitch;
use Carbon\Carbon;

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
        // Marcar todas las reservas asociadas como eliminadas
        $pitch->reservations()->update([
            'deleted_at' => Carbon::now(),
            'status' => 'cancelled',
            'cancellation_reason' => 'Campo eliminado por el establecimiento',
            'cancellation_date' => Carbon::now(),
        ]);
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
