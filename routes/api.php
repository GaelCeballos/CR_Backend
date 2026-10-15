<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BitacoraController;
use App\Http\Controllers\Api\V1\CierreAnioController;
use App\Http\Controllers\Api\V1\ExpedienteController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PacienteController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de la API (v1)
|--------------------------------------------------------------------------
|
| Publicas: solo el login. Todo lo demas requiere un token Bearer vigente
| (auth:sanctum) y una cuenta activa (middleware "active"). Mientras la
| contraseña sea temporal (middleware "no-temp-password"), solo /me,
| /me/password, /auth/logout y /auth/me quedan disponibles.
|
*/

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::put('me', [MeController::class, 'update']);
        Route::post('me/password', [MeController::class, 'changePassword']);

        Route::middleware('no-temp-password')->group(function () {
            // El listado (GET /users) decide internamente si es la vista
            // "selector" (cualquier usuario) o la tabla completa (solo admin).
            Route::get('users', [UserController::class, 'index']);

            Route::middleware('role:admin')->group(function () {
                Route::post('users', [UserController::class, 'store']);
                Route::get('users/{user}', [UserController::class, 'show']);
                Route::put('users/{user}', [UserController::class, 'update']);
                Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);
                Route::get('bitacora', [BitacoraController::class, 'index']);

                // Sustituyen la descarga binaria de PDF/Excel: el frontend ya
                // construye esos archivos; aqui solo se entregan los datos.
                Route::get('expedientes/{clave}/pdf', [ExpedienteController::class, 'datosParaPdf']);
                Route::post('expedientes/{clave}/entrega', [ExpedienteController::class, 'marcarEntrega']);
                Route::get('export/excel', [ExportController::class, 'excelData']);

                // Cierre y respaldo anual: el admin descarga el Excel completo
                // de un año pendiente y, al confirmar, esos registros se borran.
                Route::get('admin/anios/pendientes', [CierreAnioController::class, 'pendientes']);
                Route::post('admin/anios/{anio}/cerrar', [CierreAnioController::class, 'cerrar']);
            });

            Route::get('pacientes/buscar', [PacienteController::class, 'buscar']);
            Route::get('pacientes/lookup', [PacienteController::class, 'lookup']);

            Route::get('expedientes/siguiente-folio', [ExpedienteController::class, 'siguienteFolio']);
            Route::get('expedientes/estadisticas', [ExpedienteController::class, 'estadisticas']);
            Route::get('expedientes/empresas', [ExpedienteController::class, 'empresas']);
            Route::get('expedientes', [ExpedienteController::class, 'index']);
            Route::post('expedientes', [ExpedienteController::class, 'store']);
            Route::get('expedientes/{clave}', [ExpedienteController::class, 'show']);
            Route::put('expedientes/{clave}', [ExpedienteController::class, 'update']);
        });
    });
});
