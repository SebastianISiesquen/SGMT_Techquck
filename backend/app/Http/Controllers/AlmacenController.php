<?php

namespace App\Http\Controllers;

use App\Models\AlmacenArticulo;
use App\Models\AlmacenMovimiento;
use App\Models\Proyecto;
use App\Services\AlmacenService;
use App\Services\Auditor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Almacén de la empresa: consumibles (pasta térmica, limpia contactos, conectores…)
 * y equipos/repuestos (discos, fuentes, cámaras de reemplazo). Las hojas de servicio
 * descuentan del stock lo que el técnico usó.
 */
class AlmacenController extends Controller
{
    public function index(Request $request)
    {
        $articulos = AlmacenArticulo::query()
            ->filtrar($request->all())
            ->when(! $request->boolean('inactivos'), fn ($q) => $q->where('activo', true))
            ->orderBy('tipo')->orderBy('nombre')
            ->paginate(25)->withQueryString();

        return view('almacen.index', [
            'articulos' => $articulos,
            'resumen' => [
                'articulos' => AlmacenArticulo::where('activo', true)->count(),
                'bajo_minimo' => AlmacenArticulo::where('activo', true)->bajoMinimo()->count(),
                'valor' => (float) AlmacenArticulo::where('activo', true)->sum(DB::raw('stock * COALESCE(costo_unitario, 0)')),
                'salidas_mes' => AlmacenMovimiento::where('tipo', 'salida')->where('fecha', '>=', now()->startOfMonth())->count(),
            ],
            'categorias' => AlmacenArticulo::whereNotNull('categoria')->distinct()->orderBy('categoria')->pluck('categoria'),
        ]);
    }

    public function create(Request $request)
    {
        return view('almacen.form', $this->datosFormulario(new AlmacenArticulo([
            'tipo' => $request->tipo === 'repuesto' ? 'repuesto' : 'consumible',
            'unidad' => 'und',
        ])));
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);
        $inicial = (float) $request->input('stock_inicial', 0);

        $articulo = DB::transaction(function () use ($datos, $inicial, $request) {
            $articulo = AlmacenArticulo::create($datos);
            if ($inicial > 0) {
                AlmacenService::movimiento($articulo, 'entrada', $inicial, [
                    'motivo' => 'Stock inicial', 'costo_unitario' => $datos['costo_unitario'] ?? null, 'documento' => $request->documento,
                ]);
            }

            return $articulo;
        });

        Auditor::log('crear', 'almacen', "Registró el artículo {$articulo->codigo} · {$articulo->nombre}", $articulo);

        return redirect()->route('almacen.show', $articulo)->with('ok', "Artículo {$articulo->codigo} registrado.");
    }

    public function show(AlmacenArticulo $articulo)
    {
        $movimientos = $articulo->movimientos()->with(['usuario', 'hojaServicio', 'proyecto'])->paginate(30);

        return view('almacen.show', [
            'articulo' => $articulo,
            'movimientos' => $movimientos,
            'proyectos' => Proyecto::with('entidad')->enCurso()->orderBy('nombre')->get()
                ->mapWithKeys(fn ($p) => [$p->id => "{$p->codigo} · {$p->nombre}"]),
        ]);
    }

    public function edit(AlmacenArticulo $articulo)
    {
        return view('almacen.form', $this->datosFormulario($articulo));
    }

    public function update(Request $request, AlmacenArticulo $articulo)
    {
        $articulo->update($this->validar($request, $articulo));
        Auditor::log('editar', 'almacen', "Editó el artículo {$articulo->codigo}", $articulo, Auditor::cambios($articulo));

        return redirect()->route('almacen.show', $articulo)->with('ok', 'Artículo actualizado.');
    }

    public function destroy(AlmacenArticulo $articulo)
    {
        if ($articulo->movimientos()->where('tipo', 'salida')->exists()) {
            return back()->withErrors(['general' => 'El artículo tiene salidas registradas; desactívelo en lugar de eliminarlo para conservar el kardex.']);
        }

        $articulo->delete();
        Auditor::log('eliminar', 'almacen', "Eliminó el artículo {$articulo->codigo}", $articulo);

        return redirect()->route('almacen.index')->with('ok', 'Artículo eliminado.');
    }

    /** Entrada (compra), salida manual (entrega a un proyecto) o ajuste por conteo físico. */
    public function movimiento(Request $request, AlmacenArticulo $articulo)
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(AlmacenMovimiento::TIPOS))],
            'cantidad' => ['required_unless:tipo,ajuste', 'nullable', 'numeric', 'gt:0', 'max:1000000'],
            'stock_contado' => ['required_if:tipo,ajuste', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'costo_unitario' => \App\Support\Reglas::monto(),
            'documento' => \App\Support\Reglas::codigoDocumento(),
            'proyecto_id' => ['nullable', 'exists:proyectos,id'],
            'motivo' => ['required_if:tipo,ajuste', 'required_if:tipo,salida', 'nullable', 'string', 'max:255'],
        ], [
            'stock_contado.required_if' => 'Indique la cantidad contada físicamente.',
            'motivo.required_if' => 'Indique el motivo.',
        ]);

        // El ajuste se registra como la diferencia entre lo contado y lo que dice el sistema.
        $cantidad = $datos['tipo'] === 'ajuste' ? round($datos['stock_contado'] - $articulo->stock, 2) : (float) $datos['cantidad'];
        if ($datos['tipo'] === 'ajuste' && $cantidad == 0) {
            return back()->with('ok', 'El stock contado coincide con el sistema: no se registró ajuste.');
        }

        $mov = AlmacenService::movimiento($articulo, $datos['tipo'], $cantidad, $datos);
        Auditor::log('editar', 'almacen', "{$mov->tipoLabel()} de ".AlmacenArticulo::cantidad(abs($cantidad))." {$articulo->unidad} de {$articulo->codigo}", $articulo);

        return back()->with('ok', "{$mov->tipoLabel()} registrada. Stock actual: ".AlmacenArticulo::cantidad($mov->stock_resultante)." {$articulo->unidad}.");
    }

    /** Artículos con stock para el selector de materiales de la hoja de servicio. */
    public function opciones(Request $request)
    {
        $u = $request->user();
        abort_unless($u->can('hojas.registrar') || $u->can('almacen.ver'), 403);

        return AlmacenArticulo::where('activo', true)
            ->filtrar(['buscar' => $request->q])
            ->orderBy('nombre')->limit(100)->get()
            ->map(fn (AlmacenArticulo $a) => [
                'id' => $a->id,
                'codigo' => $a->codigo,
                'texto' => "{$a->nombreCompleto()} · stock ".AlmacenArticulo::cantidad($a->stock)." {$a->unidad}",
                'nombre' => $a->nombreCompleto(),
                'unidad' => $a->unidad,
                'stock' => $a->stock,
                'tipo' => $a->tipo,
            ]);
    }

    private function datosFormulario(AlmacenArticulo $articulo): array
    {
        return [
            'articulo' => $articulo,
            'categorias' => AlmacenArticulo::whereNotNull('categoria')->distinct()->orderBy('categoria')->pluck('categoria')
                ->merge(['Limpieza', 'Conectividad', 'Almacenamiento', 'Energía', 'Cámaras y CCTV', 'Redes', 'Cómputo', 'Herramientas'])->unique()->values(),
        ];
    }

    private function validar(Request $request, ?AlmacenArticulo $articulo = null): array
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(AlmacenArticulo::TIPOS))],
            'nombre' => ['required', 'string', 'max:255'],
            'codigo' => ['nullable', 'string', 'max:20', Rule::unique('almacen_articulos')->ignore($articulo?->id)],
            'categoria' => ['nullable', 'string', 'max:60'],
            'marca' => ['nullable', 'string', 'max:80'],
            'modelo' => ['nullable', 'string', 'max:80'],
            'unidad' => ['required', 'string', 'max:20'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'costo_unitario' => \App\Support\Reglas::monto(),
            'ubicacion' => ['nullable', 'string', 'max:100'],
            'stock_inicial' => ['nullable', 'numeric', 'min:0'],
        ]);
        unset($datos['stock_inicial']);

        return [...$datos, 'stock_minimo' => $datos['stock_minimo'] ?? 0, 'activo' => $request->boolean('activo', true)];
    }
}
