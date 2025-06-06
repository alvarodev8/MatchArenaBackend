<?php

namespace App\Services;

use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para manejar la lógica de disponibilidad de reservas.
 * @author Álvaro Coo Igeño
 */
class ReservationService
{
    // Duración mínima de una reserva en minutos
    private const MIN_RESERVATION_DURATION = 60;

    /**
     * Verifica si un horario está disponible para un campo.
     *
     * @param int $pitchId ID del campo.
     * @param Carbon $startAt Fecha y hora de inicio.
     * @param int $duration Duración en minutos.
     * @param Request $request Request para logging.
     * @return array Respuesta con disponibilidad y mensaje opcional.
     */
    public function checkAvailability(int $pitchId, Carbon $startAt, int $duration, Request $request): array
    {
        try {
            $endAt = $startAt->copy()->addMinutes($duration);
            $conflict = $this->findConflictingReservation($pitchId, $startAt, $endAt, $request);

            if ($conflict) {
                $conflictStart = $conflict->start_at->format('d/m/Y H:i');
                $conflictEnd = $conflict->start_at->copy()->addMinutes($conflict->duration)->format('d/m/Y H:i');

                Log::warning('Horario no disponible', [
                    'pitch_id' => $pitchId,
                    'start_at' => $startAt->toDateTimeString(),
                    'duration' => $duration,
                    'conflict_start' => $conflictStart,
                    'conflict_end' => $conflictEnd,
                    'ip' => $request->ip(),
                ]);

                return [
                    'available' => false,
                    'message' => "El campo está ocupado desde $conflictStart hasta $conflictEnd."
                ];
            }

            return ['available' => true];
        } catch (\Exception $e) {
            Log::error('Error al verificar disponibilidad', ['error' => $e->getMessage(), 'ip' => $request->ip()]);
            return ['available' => false, 'message' => 'Error interno'];
        }
    }

    /**
     * Obtiene los horarios disponibles para un campo en una fecha.
     *
     * @param int $pitchId ID del campo.
     * @param Carbon $date Fecha para verificar.
     * @param Request $request Request para logging.
     * @return array Lista de horarios disponibles (HH:mm).
     */
    public function getAvailableTimes(int $pitchId, Carbon $date, Request $request): array
    {
        try {
            $timeSlots = $this->generateTimeSlots($date);
            $availableTimes = [];

            foreach ($timeSlots as $time) {
                $startAt = $date->copy()->setTimeFromTimeString($time);
                $endAt = $startAt->copy()->addMinutes(self::MIN_RESERVATION_DURATION);

                // Verificar si el intervalo permite una reserva mínima sin solaparse
                if (!$this->findConflictingReservation($pitchId, $startAt, $endAt, $request)) {
                    $availableTimes[] = $time;
                } else {
                    Log::debug('Horario no disponible debido a conflicto', [
                        'pitch_id' => $pitchId,
                        'start_at' => $startAt->toDateTimeString(),
                        'end_at' => $endAt->toDateTimeString(),
                    ]);
                }
            }

            Log::info('Horarios disponibles obtenidos', [
                'pitch_id' => $pitchId,
                'date' => $date->toDateString(),
                'count' => count($availableTimes),
                'ip' => $request->ip(),
            ]);

            return $availableTimes;
        } catch (\Exception $e) {
            Log::error('Error al obtener horarios disponibles', ['error' => $e->getMessage(), 'ip' => $request->ip()]);
            return [];
        }
    }

    /**
     * Obtiene las fechas disponibles para un campo en un rango.
     *
     * @param int $pitchId ID del campo.
     * @param Carbon $startDate Fecha de inicio.
     * @param Carbon $endDate Fecha de fin.
     * @param Request $request Request para logging.
     * @return array Lista de fechas con disponibilidad.
     */
    public function getAvailableDates(int $pitchId, Carbon $startDate, Carbon $endDate, Request $request): array
    {
        try {
            $endDate = $endDate->min(Carbon::now()->addDays(15)->endOfDay());
            $dates = [];

            for ($date = $startDate->copy()->startOfDay(); $date <= $endDate; $date->addDay()) {
                $hasAvailability = false;
                foreach ($this->generateTimeSlots($date) as $time) {
                    $startAt = $date->copy()->setTimeFromTimeString($time);
                    $endAt = $startAt->copy()->addMinutes(self::MIN_RESERVATION_DURATION);

                    if (!$this->findConflictingReservation($pitchId, $startAt, $endAt, $request)) {
                        $hasAvailability = true;
                        break; // Si hay al menos un horario disponible, marcamos el día como disponible
                    }
                }

                $dates[] = [
                    'date' => $date->format('Y-m-d'),
                    'available' => $hasAvailability
                ];
            }

            Log::info('Fechas disponibles obtenidas', [
                'pitch_id' => $pitchId,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'count' => count($dates),
                'ip' => $request->ip(),
            ]);

            return $dates;
        } catch (\Exception $e) {
            Log::error('Error al obtener horarios disponibles', ['error' => $e->getMessage(), 'ip' => $request->ip()]);
            return [];
        }
    }

    /**
     * Busca una reserva que se solape con el horario dado.
     *
     * @param int $pitchId ID del campo.
     * @param Carbon $startAt Inicio del horario.
     * @param Carbon $endAt Fin del horario.
     * @param Request $request Request para contexto.
     * @return Reservation|null Reserva en conflicto o null si no hay.
     */
    private function findConflictingReservation(int $pitchId, Carbon $startAt, Carbon $endAt, Request $request): ?Reservation
    {
        return Reservation::where('pitch_id', $pitchId)
            ->where('start_at', '<', $endAt)
            ->whereRaw('DATE_ADD(start_at, INTERVAL duration MINUTE) > ?', [$startAt])
            ->whereNull('cancellation_date')
            ->first();
    }

    /**
     * Genera una lista de horarios disponibles (8:00-22:00, intervalos de 30 min).
     *
     * @param Carbon $date Fecha para los horarios.
     * @return array Lista de horarios (HH:mm).
     */
    private function generateTimeSlots(Carbon $date): array
    {
        $timeSlots = [];
        for ($hour = 8; $hour < 22; $hour++) {
            $timeSlots[] = sprintf("%02d:00", $hour);
            $timeSlots[] = sprintf("%02d:30", $hour);
        }

        // Filtrar horarios pasados si es el día actual
        if ($date->isSameDay(Carbon::now())) {
            $now = Carbon::now();
            $nextHalfHour = ceil($now->minute / 30) * 30;
            $startHour = $now->hour + ($nextHalfHour >= 60 ? 1 : ($nextHalfHour === 0 ? 0 : 1));

            $timeSlots = array_filter($timeSlots, function ($time) use ($startHour) {
                $hour = (int)explode(':', $time)[0];
                return $hour >= $startHour;
            });
        }

        return array_values($timeSlots);
    }
}
