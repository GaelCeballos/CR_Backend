<?php

namespace App\Enums;

/**
 * Tipo de evento que puede registrarse en la bitacora.
 */
enum AccionAuditoria: string
{
    case CreacionDeFolio = 'Creación de Folio';
    case EdicionDeExpediente = 'Edición de Expediente';
    case Consulta = 'Consulta';
    case ImportacionHistorica = 'Importación Histórica';
}
