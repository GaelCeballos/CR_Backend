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

    /** Descarga del respaldo anual o borrado definitivo de un año (no es de un folio). */
    case CierreDeAnio = 'Cierre de Año';
}
