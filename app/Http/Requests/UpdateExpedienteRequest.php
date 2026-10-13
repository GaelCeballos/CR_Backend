<?php

namespace App\Http\Requests;

/**
 * La edicion de un expediente recibe el mismo payload plano que el alta, por
 * eso reutiliza exactamente las mismas reglas de validacion.
 */
class UpdateExpedienteRequest extends StoreExpedienteRequest {}
