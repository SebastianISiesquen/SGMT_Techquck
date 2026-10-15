<?php

namespace App\Http\Controllers;

use App\Enums\EstadoGarantia;
use App\Models\Entidad;
use App\Models\Garantia;
use App\Models\Proyecto;
use App\Services\AlertaGarantiaService;
use App\Services\ArchivoService;
use App\Services\Auditor;
use App\Support\Ajustes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GarantiaController extends Controller
{
    public function index(Request $request)
    {
        $garantias = Garantia::query()
            ->with(['proyecto.entidad', 'proyecto.clienteFinal'])
            ->withCount(['incidencias as incidencias_abiertas_count' => fn ($q) => $q->abiertas()])
            ->estado($request->estado)
            ->when($request->buscar, fn ($q, $v) => $q->where(fn ($q) => $q
                ->where('codigo', 'like', "%$v%")->orWhere('descripcion', 'like', "%$v%")->orWhere('orden_compra', 'like', "%$v%")
                ->orWhereHas('proyecto', fn ($q) => $q->where('nombre', 'like', "%$v%"))))
            ->when($request->entidad_id, fn ($q, $v) => $q->whereHas('proyecto', fn ($q) => $q->where('entidad_id', $v)))
            ->when($request->proyecto_id, fn ($q, $v) => $q->where('proyecto_id', $v))
            ->when($request->tipo, fn ($q, $v) => $q->where('tipo', $v))
            ->when($request->vence_desde, fn ($q, $v) => $q->whereDate('fecha_vencimiento', '>=', $v))
            ->when($request->vence_hasta, fn ($q, $v) => $q->whereDate('fecha_vencimiento', '<=', $v))
            ->orderBy('fecha_vencimiento')
            ->paginate(15)->withQueryString();

        $resumen = [
            'vigente' => Garantia::vigentes()->count(),
            'por_vencer' => Garantia::porVencer()->count(),
            'vencida' => Garantia::vencidas()->count(),
        ];

        return view('garantias.index', [
            'garantias' => $garantias,
            'resumen' => $resumen,
            'estados' => EstadoGarantia::options(),
            'entidades' => Entidad::orderBy('nombre')->pluck('nombre', 'id'),
            'tipos' => Ajustes::catalogo('tipos_garantia'),
        ]);
    }

    public function create(Request $request)
    {
        $proyecto = Proyecto::find($request->proyecto_id);

        return view('garantias.form', $this->datosFormulario(new Garantia([
            'proyecto_id' => $proyecto?->id,
            'orden_compra' => $proyecto?->orden_compra,
            'fecha_inicio' => $proyecto?->fecha_entrega,
            // Implementaciones: 5 años por defecto; servicios: 3 meses.
            'duracion_meses' => $proyecto?->esServicio() ? 3 : 60,
            'fecha_vencimiento' => $proyecto?->fecha_entrega?->copy()->addMonthsNoOverflow($proyecto->esServicio() ? 3 : 60),
            'notificar_cliente' => true,
        ])));
    }

    public function store(Request $request)
    {
        $garantia = DB::transaction(function () use ($request) {
            $garantia = Garantia::create($this->validar($request));
            $garantia->proyecto->equipos()->whereNull('garantia_id')->update(['garantia_id' => $garantia->id]);

            foreach ($request->file('archivos', []) as $file) {
                ArchivoService::guardar($file, $garantia, ArchivoService::categoriaPara($file, 'carta_garantia'));
            }

            return $garantia;
        });

        Auditor::log('crear', 'garantias', "Registró la garantía {$garantia->codigo}", $garantia);

        return redirect()->route('garantias.show', $garantia)->with('ok', 'Garantía registrada.');
    }

    public function show(Garantia $garantia)
    {
        $garantia->load(['proyecto.entidad', 'proyecto.clienteFinal', 'archivos.autor', 'alertas', 'equipos',
            'incidencias' => fn ($q) => $q->with(['tecnico', 'equipo'])->latest('fecha_reporte')]);

        $alertas = app(AlertaGarantiaService::class);

        return view('garantias.show', [
            'garantia' => $garantia,
            'correosCliente' => $alertas->correosCliente($garantia),
            'hitos' => AlertaGarantiaService::hitos(),
        ]);
    }

    /** Carta / certificado de garantía generado con los datos registrados (PDF). */
    public function carta(Garantia $garantia)
    {
        $garantia->load(['proyecto.entidad', 'proyecto.clienteFinal', 'equipos']);
        Auditor::log('exportar', 'garantias', "Emitió la carta de garantía {$garantia->codigo}", $garantia);

        return Pdf::loadView('pdf.carta-garantia', ['g' => $garantia, 'p' => $garantia->proyecto])
            ->setPaper('a4')
            ->stream("Carta-garantia-{$garantia->codigo}.pdf");
    }

    /** Envía ahora el aviso de vencimiento al cliente (sin esperar al proceso diario). */
    public function avisar(Garantia $garantia, AlertaGarantiaService $alertas)
    {
        $garantia->load(['proyecto.entidad', 'proyecto.clienteFinal', 'proyecto.responsable']);
        $correos = $alertas->correosCliente($garantia);

        if (! $correos) {
            return back()->withErrors(['general' => 'El cliente no tiene correos registrados o la garantía está configurada para no avisarle.']);
        }

        $alertas->enviar($garantia, null, soloCliente: true);
        Auditor::log('notificar', 'garantias', "Envió el aviso de vencimiento de {$garantia->codigo} a ".implode(', ', $correos), $garantia);

        return back()->with('ok', 'Aviso enviado a '.implode(', ', $correos).'.');
    }

    public function edit(Garantia $garantia)
    {
        return view('garantias.form', $this->datosFormulario($garantia));
    }

    public function update(Request $request, Garantia $garantia)
    {
        $garantia->update($this->validar($request));
        Auditor::log('editar', 'garantias', "Editó la garantía {$garantia->codigo}", $garantia, Auditor::cambios($garantia));

        return redirect()->route('garantias.show', $garantia)->with('ok', 'Garantía actualizada.');
    }

    public function destroy(Garantia $garantia)
    {
        if ($garantia->incidencias()->exists()) {
            return back()->withErrors(['general' => 'No se puede eliminar una garantía con incidencias. Puede marcarla como anulada.']);
        }

        $garantia->delete();
        Auditor::log('eliminar', 'garantias', "Eliminó la garantía {$garantia->codigo}", $garantia);

        return redirect()->route('proyectos.show', $garantia->proyecto_id)->with('ok', 'Garantía eliminada.');
    }

    private function datosFormulario(Garantia $garantia): array
    {
        return [
            'garantia' => $garantia,
            'proyectos' => Proyecto::with('entidad')->enCurso()->orWhere('id', $garantia->proyecto_id)->orderBy('nombre')->get()
                ->mapWithKeys(fn ($p) => [$p->id => "{$p->codigo} · {$p->nombre} ({$p->entidad->nombre})"]),
            'tipos' => Ajustes::catalogo('tipos_garantia'),
        ];
    }

    private function validar(Request $request): array
    {
        $request->validate([
            'archivos' => ['nullable', 'array', 'max:10'],
            'archivos.*' => ArchivoService::reglas(),
        ]);

        $datos = $request->validate([
            'proyecto_id' => ['required', 'exists:proyectos,id'],
            'tipo' => ['required', 'string', 'max:40'],
            'descripcion' => ['required', 'string', 'max:255'],
            'cobertura' => ['nullable', 'string'],
            'condiciones' => ['nullable', 'string'],
            'proveedor' => ['nullable', 'string', 'max:255'],
            'numero_documento' => ['nullable', 'string', 'max:60'],
            'orden_compra' => \App\Support\Reglas::codigoDocumento(),
            'monto' => \App\Support\Reglas::monto(),
            'fecha_inicio' => ['required', 'date'],
            'duracion_meses' => ['nullable', 'integer', 'min:1', 'max:240'],
            'fecha_vencimiento' => ['required', 'date', 'after:fecha_inicio'],
            'observaciones' => ['nullable', 'string', 'max:3000'],
        ]);

        return [
            ...$datos,
            'anulada' => $request->boolean('anulada'),
            'notificar_cliente' => $request->boolean('notificar_cliente'),
        ];
    }
}
