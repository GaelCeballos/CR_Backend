<?php

namespace App\Enums;

enum AccionAuditoria: string
{
    case CreacionDeFolio = 'Creación de Folio';
    case EdicionDeExpediente = 'Edición de Expediente';
    case Consulta = 'Consulta';
}
