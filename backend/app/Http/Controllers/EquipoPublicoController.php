<?php

namespace App\Http\Controllers;

use App\Models\Equipo;
use App\Models\HojaServicio;
use App\Models\Mantenimiento;
use App\Support\Ajustes;

/**
 * Ficha pública del equipo: es lo que abre el QR del sticker con la cámara de cualquier celular,
 * sin iniciar sesión. Muestra datos, garantía e historial (fallas, visitas, mantenimientos).
 * El enlace usa un código aleatorio, así no se pueden recorrer los demás equipos.
 */
class EquipoPublicoController extends Controller
{
    public function show(string $token)
    {
        $equipo = Equipo::where('token_publico', $token)->firstOrFail();
        $equipo->load([
            'entidad', 'proyecto',
            'eventos' => fn ($q) => $q->with(['usuario', 'incidencia', 'hojaServicio'])->limit(80),
            'incidencias' => fn ($q) => $q->with('tecnico')->latest('fecha_reporte')->limit(20),
        ]);

        $visitas = HojaServicio::with('tecnico')
            ->whereHas('equipos', fn ($q) => $q->where('equipo_id', $equipo->id))
            ->where('estado', '!=', 'anulada')
            ->latest('fecha')->limit(20)->get();

        $proximoMantenimiento = $equipo->proyecto_id
            ? Mantenimiento::pendientes()->whereHas('plan', fn ($q) => $q->where('proyecto_id', $equipo->proyecto_id))
                ->orderBy('fecha_programada')->first()
            : null;

        return view('publico.equipo', [
            'equipo' => $equipo,
            'garantia' => $equipo->garantiaVigente(),
            'visitas' => $visitas,
            'proximoMantenimiento' => $proximoMantenimiento,
            'empresa' => Ajustes::all(),
        ]);
    }
}
