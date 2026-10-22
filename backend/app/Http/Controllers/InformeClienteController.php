<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use App\Services\Auditor;
use App\Services\InformeClienteService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Informe periódico al cliente: verlo en PDF o enviárselo por correo con un clic. */
class InformeClienteController extends Controller
{
    public function __construct(private InformeClienteService $informes) {}

    public function pdf(Request $request, Proyecto $proyecto)
    {
        [$desde, $hasta] = $this->periodo($request, $proyecto);
        $sufijo = $proyecto->esServicio() ? '-'.$desde->format('Y-m') : '';
        Auditor::log('exportar', 'proyectos', "Generó el informe".($proyecto->esServicio() ? ' '.$desde->format('m/Y') : '')." de {$proyecto->codigo}", $proyecto);

        return $this->informes->pdf($proyecto, $desde, $hasta)->stream("Informe-{$proyecto->codigo}{$sufijo}.pdf");
    }

    public function enviar(Request $request, Proyecto $proyecto)
    {
        [$desde, $hasta] = $this->periodo($request, $proyecto);
        if (! \App\Support\Correo::activo()) {
            return back()->withErrors(['general' => 'El correo no está configurado: no se envió nada. Configure el servidor de correo en Configuración → Correo y use «Enviar correo de prueba».']);
        }

        try {
            $correos = $this->informes->enviar($proyecto, $desde, $hasta);
        } catch (\Throwable $e) {
            return back()->withErrors(['general' => 'No se pudo enviar el correo: '.$e->getMessage()]);
        }

        return $correos
            ? back()->with('ok', 'Informe enviado a '.implode(', ', $correos).'.')
            : back()->withErrors(['general' => 'El cliente no tiene correos registrados. Agréguelos en la ficha del cliente.']);
    }

    /**
     * Servicios: el mes elegido (AAAA-MM; por defecto el mes en curso).
     * Implementaciones: todo el proyecto, desde el inicio de ejecución de la ficha hasta hoy.
     */
    private function periodo(Request $request, Proyecto $proyecto): array
    {
        if (! $proyecto->esServicio()) {
            $desde = ($proyecto->fecha_inicio ?? $proyecto->fecha_entrega)->copy()->startOfDay();

            return [$desde, now()->max($desde)];
        }

        $request->validate(['mes' => ['nullable', 'date_format:Y-m']]);
        $desde = $request->mes ? Carbon::createFromFormat('Y-m-d', $request->mes.'-01')->startOfDay() : now()->startOfMonth();

        return [$desde, $desde->copy()->endOfMonth()->min(now())];
    }
}
