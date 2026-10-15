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
        [$desde, $hasta] = $this->periodo($request);
        Auditor::log('exportar', 'proyectos', "Generó el informe {$desde->format('m/Y')} de {$proyecto->codigo}", $proyecto);

        return $this->informes->pdf($proyecto, $desde, $hasta)->stream("Informe-{$proyecto->codigo}-{$desde->format('Y-m')}.pdf");
    }

    public function enviar(Request $request, Proyecto $proyecto)
    {
        [$desde, $hasta] = $this->periodo($request);
        $correos = $this->informes->enviar($proyecto, $desde, $hasta);

        return $correos
            ? back()->with('ok', 'Informe enviado a '.implode(', ', $correos).'.')
            : back()->withErrors(['general' => 'El cliente no tiene correos registrados. Agréguelos en la ficha del cliente.']);
    }

    /** Mes elegido (AAAA-MM); por defecto el mes en curso. */
    private function periodo(Request $request): array
    {
        $request->validate(['mes' => ['nullable', 'date_format:Y-m']]);
        $desde = $request->mes ? Carbon::createFromFormat('Y-m-d', $request->mes.'-01')->startOfDay() : now()->startOfMonth();

        return [$desde, $desde->copy()->endOfMonth()->min(now())];
    }
}
