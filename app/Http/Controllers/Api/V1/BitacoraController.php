<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Consulta de la bitacora de accesos y cambios (solo para administradores).
 */
class BitacoraController extends Controller
{
    /**
     * Get: lista los eventos de bitacora, mas recientes primero, con
     * filtros por accion, dia y busqueda libre.
     *
     * @param  Request  $request  accion, fecha (dia en zona Mexico), q, page
     * @return JsonResponse pagina de 20 eventos
     */
    public function index(Request $request)
    {
        $query = Auditoria::query();

        if ($accion = $request->query('accion')) {
            $query->where('accion', $accion);
        }

        if ($fecha = $request->query('fecha')) {
            $inicio = Carbon::parse($fecha, 'America/Mexico_City')->startOfDay();
            $query->whereBetween('fecha', [$inicio, $inicio->copy()->endOfDay()]);
        }

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($consulta) use ($q) {
                $consulta->where('usuario_nombre', 'ilike', "%{$q}%")
                    ->orWhere('paciente', 'ilike', "%{$q}%");

                if (ctype_digit($q)) {
                    $consulta->orWhere('folio', (int) $q);
                }
            });
        }

        $total = $query->count();
        $page = (int) $request->query('page', 1);

        $eventos = $query->orderByDesc('fecha')->forPage($page, 20)->get();

        return response()->json([
            'items' => $eventos->map(fn (Auditoria $e) => [
                'fecha' => $e->fecha->toIso8601String(),
                'usuario_nombre' => $e->usuario_nombre,
                'accion' => $e->accion->value,
                'folio' => $e->folio,
                'paciente' => $e->paciente,
                'detalle' => $e->detalle,
            ]),
            'total' => $total,
        ]);
    }
}
