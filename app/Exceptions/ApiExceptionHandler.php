<?php

namespace App\Exceptions;

use App\Services\SesionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Convierte cualquier excepcion lanzada dentro de /api/* al formato de error
 * unico de la API: { "error": { "code", "message", "fields"? } }.
 */
class ApiExceptionHandler
{
    /**
     * Registra todos los "render" necesarios en el manejador de excepciones de la app.
     *
     * @param  Exceptions  $exceptions  configurador de excepciones de bootstrap/app.php
     */
    public static function register(Exceptions $exceptions): void
    {
        // Errores de negocio (credenciales, permisos, no encontrado, conflicto):
        // son respuestas esperadas, no fallas. Antes cada uno dejaba una traza
        // completa en el log (auditoria run-1, H13).
        $exceptions->dontReport(ApiException::class);

        // Un error de base de datos se registra sin valores: el mensaje de Postgres
        // trae datos del paciente en el DETAIL ("Key (curp)=(…)", "Failing row
        // contains (…)") aunque los bindings vayan enmascarados (H12).
        $exceptions->report(function (QueryException $e) {
            Log::error('Error de base de datos: '.self::resumenSinDatos($e), [
                'sqlstate' => $e->getCode(),
                'sql' => $e->getSql(),
            ]);

            return false;
        });

        $exceptions->render(function (ApiException $e, Request $request) {
            if ($request->is('api/*')) {
                return new JsonResponse($e->toResponseArray(), $e->status);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                $fields = [];
                foreach ($e->errors() as $field => $messages) {
                    $fields[$field] = $messages[0];
                }

                return new JsonResponse([
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => $fields !== [] ? array_values($fields)[0] : 'Hay errores de validación.',
                        'fields' => $fields,
                    ],
                ], 400);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                // El front usa el codigo para explicar en el login por que se cerro la sesion.
                $codigo = app(SesionService::class)->motivoRechazo($request->bearerToken());
                $mensajes = [
                    'SESSION_REPLACED' => 'Tu sesión se cerró porque tu cuenta inició sesión en otro equipo o pestaña.',
                    'SESSION_EXPIRED' => 'Tu sesión venció por inactividad. Vuelve a iniciar sesión.',
                    'UNAUTHENTICATED' => 'No has iniciado sesión.',
                ];

                return new JsonResponse([
                    'error' => ['code' => $codigo, 'message' => $mensajes[$codigo]],
                ], 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return new JsonResponse([
                    'error' => ['code' => 'FORBIDDEN', 'message' => 'No tienes permiso para esta acción.'],
                ], 403);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return new JsonResponse([
                    'error' => ['code' => 'TOO_MANY_REQUESTS', 'message' => 'Demasiados intentos. Intenta de nuevo en un momento.'],
                ], 429);
            }
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return new JsonResponse([
                    'error' => ['code' => 'NOT_FOUND', 'message' => 'No encontrado.'],
                ], 404);
            }
        });

        // Un registro duplicado casi siempre es una carrera entre dos equipos
        // guardando al mismo tiempo (p. ej. el folio, si se agotaron los
        // reintentos): se avisa con un 409 que se puede volver a intentar.
        $exceptions->render(function (UniqueConstraintViolationException $e, Request $request) {
            if ($request->is('api/*')) {
                return new JsonResponse([
                    'error' => ['code' => 'CONFLICT', 'message' => 'Otro equipo guardó al mismo tiempo. Vuelve a intentar guardar.'],
                ], 409);
            }
        });

        // Cualquier otra excepcion no prevista: se responde un 500 generico, sin
        // filtrar detalles internos al cliente. El kernel ya la registra en el log
        // antes de llegar aqui; llamar report() otra vez la duplicaba.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof ApiException || $e instanceof ValidationException
                || $e instanceof AuthenticationException || $e instanceof AuthorizationException
                || $e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException
                || $e instanceof ThrottleRequestsException || $e instanceof UniqueConstraintViolationException) {
                return null;
            }

            return new JsonResponse([
                'error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Ocurrió un error inesperado.'],
            ], 500);
        });
    }

    /**
     * Primera linea del error de Postgres sin el DETAIL ni los valores entre
     * comillas (p. ej. 'invalid input syntax for type date: "31/02/1990"'). Los
     * nombres de restricciones y columnas se conservan para poder diagnosticar.
     *
     * @param  QueryException  $e  error de la consulta
     * @return string descripcion del error apta para el log
     */
    private static function resumenSinDatos(QueryException $e): string
    {
        $mensaje = $e->getPrevious()?->getMessage() ?? '';
        $linea = trim(strtok(preg_replace('/^.*?ERROR:\s*/s', '', $mensaje), "\n") ?: '');
        $linea = preg_replace('/\s*DETAIL:.*$/s', '', $linea);

        return preg_replace_callback(
            '/"([^"]*)"/',
            fn ($m) => preg_match('/^[a-z_][a-z0-9_]*$/', $m[1]) ? $m[0] : '"…"',
            $linea,
        );
    }
}
