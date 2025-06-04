<?php

namespace App\Http\Traits;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Trait para estandarizar respuestas JSON y manejo de errores en los controladores.
 * @author Álvaro Coo Igeño
 */
trait ApiResponser
{
    /**
     * Devuelve una respuesta JSON exitosa.
     *
     * @param array $data Datos adicionales a incluir en la respuesta.
     * @param string $message Mensaje de éxito.
     * @param int $code Código HTTP.
     * @return JsonResponse
     */
    protected function successResponse(array $data = [], string $message = 'Operación exitosa', int $code = 200): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $data), $code);
    }

    /**
     * Devuelve una respuesta JSON de error.
     *
     * @param string $message Mensaje de error.
     * @param int $code Código HTTP.
     * @param array $errors Errores adicionales (por ejemplo, de validación).
     * @return JsonResponse
     */
    protected function errorResponse(string $message = 'Error en la operación', int $code = 500, array $errors = []): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => $errors], $code);
    }

    /**
     * Maneja excepciones y registra errores en el log.
     *
     * @param \Exception $e Excepción capturada.
     * @param Request $request Request actual.
     * @param string $context Contexto de la operación (por ejemplo, 'registro').
     * @return JsonResponse
     */
    protected function handleException(\Exception $e, Request $request, string $context): JsonResponse
    {
        if ($e instanceof ValidationException) {
            Log::warning("Error de validación en $context", [
                'errors' => $e->errors(),
                'ip' => $request->ip(),
            ]);
            return $this->errorResponse('Error de validación', 422, $e->errors());
        }

        if ($e instanceof ModelNotFoundException) {
            Log::warning("Recurso no encontrado en $context", [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);
            return $this->errorResponse('Recurso no encontrado', 404);
        }

        if ($e instanceof AuthenticationException || $e instanceof AuthorizationException) {
            Log::warning("Acceso denegado en $context", [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);
            return $this->errorResponse('Acceso denegado', 403);
        }

        Log::error("Error inesperado en $context", [
            'error' => $e->getMessage(),
            'ip' => $request->ip(),
            'stack_trace' => $e->getTraceAsString(),
        ]);
        return $this->errorResponse('Error inesperado', 500);
    }
}
