<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
                return new JsonResponse([
                    'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'No has iniciado sesión.'],
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

        // Cualquier otra excepcion no prevista: se registra en el log y se
        // responde un 500 generico, sin filtrar detalles internos al cliente.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof ApiException || $e instanceof ValidationException
                || $e instanceof AuthenticationException || $e instanceof AuthorizationException
                || $e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException
                || $e instanceof ThrottleRequestsException) {
                return null;
            }

            report($e);

            return new JsonResponse([
                'error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Ocurrió un error inesperado.'],
            ], 500);
        });
    }
}
