<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\Entidad;
use App\Models\Equipo;
use App\Models\Incidencia;
use App\Services\Auditor;
use App\Support\Ajustes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Cotizaciones para servicios sin cobertura: el cliente ya no tiene garantía o contrato,
 * pero pide un diagnóstico y una propuesta económica.
 */
class CotizacionController extends Controller
{
    public function index(Request $request)
    {
        $cotizaciones = Cotizacion::with(['entidad', 'incidencia', 'equipo', 'autor'])
            ->filtrar($request->all())
            ->latest('fecha')->latest('id')
            ->paginate(20)->withQueryString();

        return view('cotizaciones.index', [
            'cotizaciones' => $cotizaciones,
            'entidades' => Entidad::orderBy('nombre')->pluck('nombre', 'id'),
            'resumen' => Cotizacion::selectRaw('estado, count(*) as cantidad, sum(total) as monto')->groupBy('estado')->get()->keyBy('estado'),
        ]);
    }

    public function create(Request $request)
    {
        $incidencia = Incidencia::with(['proyecto', 'equipo', 'atenciones'])->find($request->incidencia_id);
        $equipo = $incidencia?->equipo ?? Equipo::find($request->equipo_id);
        $ultima = $incidencia?->atenciones->first();

        $cotizacion = new Cotizacion([
            'entidad_id' => $incidencia?->proyecto->entidad_id ?? $equipo?->entidad_id ?? $request->entidad_id,
            'proyecto_id' => $incidencia?->proyecto_id ?? $equipo?->proyecto_id,
            'incidencia_id' => $incidencia?->id,
            'equipo_id' => $equipo?->id,
            'fecha' => today(),
            'validez_dias' => 15,
            'moneda' => 'PEN',
            'asunto' => $incidencia ? "Servicio técnico: {$incidencia->titulo}" : ($equipo ? "Servicio técnico de {$equipo->nombre()}" : null),
            'diagnostico' => $ultima ? "Diagnóstico: {$ultima->diagnostico}" : null,
            'condiciones' => Ajustes::get('condiciones_cotizacion'),
        ]);

        $items = [['descripcion' => 'Diagnóstico técnico', 'cantidad' => 1, 'unidad' => 'servicio', 'precio_unitario' => null]];

        return view('cotizaciones.form', $this->datosFormulario($cotizacion, $items));
    }

    public function store(Request $request)
    {
        $cotizacion = DB::transaction(function () use ($request) {
            $cotizacion = Cotizacion::create([...$this->validar($request), 'user_id' => $request->user()->id]);
            $this->guardarItems($cotizacion, $request->input('items', []));

            return $cotizacion;
        });

        Auditor::log('crear', 'cotizaciones', "Elaboró la cotización {$cotizacion->numero} por {$cotizacion->monto($cotizacion->total)}", $cotizacion->entidad);

        return redirect()->route('cotizaciones.show', $cotizacion)->with('ok', 'Cotización registrada.');
    }

    public function show(Cotizacion $cotizacion)
    {
        $cotizacion->load(['entidad', 'proyecto', 'incidencia', 'equipo', 'items', 'autor']);

        return view('cotizaciones.show', compact('cotizacion'));
    }

    public function edit(Cotizacion $cotizacion)
    {
        $items = $cotizacion->items->map->only(['descripcion', 'cantidad', 'unidad', 'precio_unitario'])->all();

        return view('cotizaciones.form', $this->datosFormulario($cotizacion, $items));
    }

    public function update(Request $request, Cotizacion $cotizacion)
    {
        DB::transaction(function () use ($request, $cotizacion) {
            $cotizacion->update($this->validar($request));
            $cotizacion->items()->delete();
            $this->guardarItems($cotizacion, $request->input('items', []));
        });

        Auditor::log('editar', 'cotizaciones', "Editó la cotización {$cotizacion->numero}", $cotizacion->entidad);

        return redirect()->route('cotizaciones.show', $cotizacion)->with('ok', 'Cotización actualizada.');
    }

    public function estado(Request $request, Cotizacion $cotizacion)
    {
        $request->validate(['estado' => ['required', Rule::in(array_keys(Cotizacion::ESTADOS))]]);
        $cotizacion->update(['estado' => $request->estado]);
        Auditor::log('editar', 'cotizaciones', "Marcó la cotización {$cotizacion->numero} como {$cotizacion->estadoLabel()}", $cotizacion->entidad);

        return back()->with('ok', "Cotización marcada como {$cotizacion->estadoLabel()}.");
    }

    public function destroy(Cotizacion $cotizacion)
    {
        $cotizacion->delete();
        Auditor::log('eliminar', 'cotizaciones', "Eliminó la cotización {$cotizacion->numero}", $cotizacion->entidad);

        return redirect()->route('cotizaciones.index')->with('ok', 'Cotización eliminada.');
    }

    public function pdf(Cotizacion $cotizacion)
    {
        $cotizacion->load(['entidad', 'proyecto.clienteFinal', 'incidencia', 'equipo', 'items', 'autor']);

        return Pdf::loadView('pdf.cotizacion', ['c' => $cotizacion])->setPaper('a4')->stream("{$cotizacion->numero}.pdf");
    }

    private function guardarItems(Cotizacion $cotizacion, array $items): void
    {
        $cotizacion->items()->createMany(collect($items)
            ->filter(fn ($i) => filled($i['descripcion'] ?? null))
            ->map(fn ($i) => [
                'descripcion' => mb_substr($i['descripcion'], 0, 255),
                'cantidad' => max(0, (float) ($i['cantidad'] ?? 1)),
                'unidad' => mb_substr($i['unidad'] ?? 'und', 0, 20) ?: 'und',
                'precio_unitario' => max(0, (float) ($i['precio_unitario'] ?? 0)),
            ])->values()->all());

        $cotizacion->recalcular();
    }

    private function datosFormulario(Cotizacion $cotizacion, array $items): array
    {
        return [
            'cotizacion' => $cotizacion,
            'items' => old('items', $items),
            'entidades' => Entidad::where('activo', true)->orWhere('id', $cotizacion->entidad_id)->orderBy('nombre')->pluck('nombre', 'id'),
            'igv' => Ajustes::int('igv'),
        ];
    }

    private function validar(Request $request): array
    {
        $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.descripcion' => ['nullable', 'string', 'max:255'],
            'items.*.cantidad' => ['nullable', 'numeric', 'min:0'],
            'items.*.precio_unitario' => ['nullable', 'numeric', 'min:0'],
        ]);

        return $request->validate([
            'entidad_id' => ['required', 'exists:entidades,id'],
            'proyecto_id' => ['nullable', 'exists:proyectos,id'],
            'incidencia_id' => ['nullable', 'exists:incidencias,id'],
            'equipo_id' => ['nullable', 'exists:equipos,id'],
            'fecha' => ['required', 'date'],
            'validez_dias' => ['required', 'integer', 'min:1', 'max:365'],
            'moneda' => ['required', Rule::in(array_keys(Cotizacion::MONEDAS))],
            'asunto' => ['required', 'string', 'max:255'],
            'diagnostico' => ['nullable', 'string', 'max:5000'],
            'condiciones' => ['nullable', 'string', 'max:3000'],
        ]);
    }
}
