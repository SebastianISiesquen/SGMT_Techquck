<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auditor;
use App\Services\ArchivoService;
use App\Support\Ajustes;
use App\Support\Correo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

/** Configuración general y catálogos del sistema. */
class ConfiguracionController extends Controller
{
    public function edit()
    {
        return view('admin.configuracion', [
            'ajustes' => Ajustes::all(),
            'correoActivo' => Correo::activo(),
            'mailerEnv' => env('MAIL_MAILER'),
            'logo' => \App\Services\Pdf\Imagenes::logo(),
        ]);
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
            'verificaciones_previas' => ['nullable', 'string', 'max:3000'],
            'recomendaciones_generales' => ['nullable', 'string', 'max:3000'],
            'url_publica' => ['nullable', 'url', 'max:255'],
            'hora_alertas' => ['required', 'date_format:H:i'],
            'hora_informes' => ['required', 'date_format:H:i'],
            'correo_host' => ['nullable', 'string', 'max:255'],
            'correo_puerto' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'correo_cifrado' => ['nullable', 'in:tls,ssl'],
            'correo_usuario' => ['nullable', 'string', 'max:255'],
            'correo_remitente' => ['nullable', 'email', 'max:255'],
            'correo_nombre' => ['nullable', 'string', 'max:255'],
        ], [
            'avisos_garantia.regex' => 'Escriba los días separados por comas, por ejemplo: 60,30,15,7',
        ]);

        $datos['requerir_acta_cierre'] = $request->boolean('requerir_acta_cierre') ? 1 : 0;
        $datos['avisar_cliente_garantia'] = $request->boolean('avisar_cliente_garantia') ? 1 : 0;
        $datos['avisos_garantia'] = collect(explode(',', $datos['avisos_garantia']))->map(fn ($d) => (int) trim($d))
            ->filter()->unique()->sortDesc()->implode(',');

        $request->validate([
            'correo_clave' => ['nullable', 'string', 'max:255'],
            'empresa_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
        ], [], ['empresa_logo' => 'logo']);

        // La contraseña del correo se guarda cifrada; vacía = se conserva la anterior
        if ($request->filled('correo_clave')) {
            $datos['correo_clave'] = Correo::cifrarClave($request->correo_clave);
        }
        if (! $request->filled('correo_host')) {
            $datos['correo_clave'] = '';
        }
        $datos['url_publica'] = rtrim((string) ($datos['url_publica'] ?? ''), '/');

        // Logo de la empresa para los PDF
        $disco = Storage::disk(ArchivoService::disco());
        if ($request->hasFile('empresa_logo')) {
            $ruta = 'configuracion/logo.'.$request->file('empresa_logo')->extension();
            $disco->putFileAs('configuracion', $request->file('empresa_logo'), basename($ruta));
            $datos['empresa_logo'] = $ruta;
        } elseif ($request->boolean('quitar_logo')) {
            $datos['empresa_logo'] = '';
        }

        Ajustes::set($datos);
        Auditor::log('editar', 'configuracion', 'Actualizó la configuración general');

        return back()->with('ok', 'Configuración guardada.');
    }

    /** Envía un correo de prueba con la configuración actual y muestra el resultado real. */
    public function probarCorreo(Request $request)
    {
        $request->validate(['destino' => ['required', 'email']]);

        if (! Correo::activo()) {
            return back()->withErrors(['general' => 'El correo no está configurado: complete el servidor SMTP y guarde la configuración antes de probar.']);
        }

        $error = Correo::probar($request->destino);
        Auditor::log('notificar', 'configuracion', "Envió un correo de prueba a {$request->destino}".($error ? ' (falló)' : ''));

        return $error
            ? back()->withErrors(['general' => "El servidor de correo rechazó el envío: $error"])
            : back()->with('ok', "Correo de prueba enviado a {$request->destino}. Revise la bandeja de entrada (y la carpeta de spam).");
    }
}
