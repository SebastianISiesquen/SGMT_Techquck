<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auditor;
use App\Support\Ajustes;
use Illuminate\Http\Request;

/** Configuración general y catálogos del sistema. */
class ConfiguracionController extends Controller
{
    public function edit()
    {
        return view('admin.configuracion', ['ajustes' => Ajustes::all()]);
    }

    public function update(Request $request)
    {
        $datos = $request->validate([
            'empresa_nombre' => ['required', 'string', 'max:255'],
            'empresa_ruc' => \App\Support\Reglas::ruc(),
            'empresa_direccion' => ['nullable', 'string', 'max:255'],
            'empresa_telefono' => \App\Support\Reglas::telefono(),
            'empresa_email' => ['nullable', 'email', 'max:255'],
            'empresa_representante' => ['nullable', 'string', 'max:255'],
            'empresa_cargo_representante' => ['nullable', 'string', 'max:255'],
            'dias_alerta_garantia' => ['required', 'integer', 'min:1', 'max:365'],
            'avisos_garantia' => ['required', 'string', 'max:60', 'regex:/^\s*\d{1,3}(\s*,\s*\d{1,3})*\s*$/'],
            'dias_alerta_mantenimiento' => ['required', 'integer', 'min:1', 'max:90'],
            'igv' => ['required', 'integer', 'min:0', 'max:50'],
            'anios_historial' => ['required', 'integer', 'min:1', 'max:20'],
            'dias_incidencia_sin_atender' => ['required', 'integer', 'min:1', 'max:60'],
            'max_archivo_mb' => ['required', 'integer', 'min:1', 'max:100'],
            'sectores' => ['required', 'string'],
            'categorias_equipo' => ['required', 'string'],
            'condiciones_cotizacion' => ['nullable', 'string', 'max:3000'],
            'tipos_proyecto' => ['required', 'string'],
            'tipos_servicio' => ['required', 'string'],
            'tipos_garantia' => ['required', 'string'],
            'canales_reporte' => ['required', 'string'],
        ], [
            'avisos_garantia.regex' => 'Escriba los días separados por comas, por ejemplo: 60,30,15,7',
        ]);

        $datos['requerir_acta_cierre'] = $request->boolean('requerir_acta_cierre') ? 1 : 0;
        $datos['avisar_cliente_garantia'] = $request->boolean('avisar_cliente_garantia') ? 1 : 0;
        $datos['avisos_garantia'] = collect(explode(',', $datos['avisos_garantia']))->map(fn ($d) => (int) trim($d))
            ->filter()->unique()->sortDesc()->implode(',');

        Ajustes::set($datos);
        Auditor::log('editar', 'configuracion', 'Actualizó la configuración general');

        return back()->with('ok', 'Configuración guardada.');
    }
}
