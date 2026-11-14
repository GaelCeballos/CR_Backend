<?php

use Illuminate\Support\Facades\Route;

// La API no tiene paginas: la raiz responde un JSON simple. Antes intentaba
// mostrar la vista 'welcome', que no existe, y cada visita anonima dejaba un
// error 500 con traza completa en el log (auditoria run-1, H13). Sin el grupo
// 'web': no necesita sesion, y con SESSION_DRIVER=database cada visita anonima
// (bots, monitores) creaba una fila en la tabla sessions.
Route::get('/', fn () => response()->json(['servicio' => 'API CRM Cruz Roja Colima', 'estado' => 'ok']))
    ->withoutMiddleware('web');
