<?php

namespace App\Http\Controllers;

use App\Models\Entidad;
use App\Services\Auditor;
use App\Support\Ajustes;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Clientes de la empresa: entidades del Estado y empresas privadas, clasificados por sector. */
class EntidadController extends Controller
{
    public function index(Request $request)
    {
        $entidades = Entidad::query()
            ->withCount(['proyectos', 'garantias', 'equipos'])
            ->when($request->buscar, fn ($q, $v) => $q->where(fn ($q) => $q->where('nombre', 'like', "%$v%")->orWhere('ruc', 'like', "%$v%")))
            ->when($request->naturaleza, fn ($q, $v) => $q->where('naturaleza', $v))
            ->when($request->sector, fn ($q, $v) => $q->where('sector', $v))
            ->orderBy('nombre')
            ->paginate(15)->withQueryString();

        return view('entidades.index', ['entidades' => $entidades, 'sectores' => Ajustes::catalogo('sectores')]);
    }

    public function create()
    {
        return view('entidades.form', ['entidad' => new Entidad(['activo' => true]), 'sectores' => Ajustes::catalogo('sectores')]);
    }

    public function store(Request $request)
    {
        $entidad = Entidad::create($this->validar($request));
        Auditor::log('crear', 'entidades', "Registró el cliente {$entidad->nombre}", $entidad);

        return redirect()->route('entidades.show', $entidad)->with('ok', 'Cliente registrado.');
    }

    public function show(Entidad $entidad)
    {
        $entidad->load([
            'proyectos' => fn ($q) => $q->with('clienteFinal')->withCount('garantias')->orderByDesc('fecha_entrega'),
            'proyectosComoClienteFinal' => fn ($q) => $q->with('entidad')->orderByDesc('fecha_entrega'),
        ])->loadCount(['equipos', 'equipos as equipos_con_falla_count' => fn ($q) => $q->whereIn('estado', ['con_falla', 'en_reparacion', 'inoperativo'])]);

        return view('entidades.show', compact('entidad'));
    }

    public function edit(Entidad $entidad)
    {
        return view('entidades.form', ['entidad' => $entidad, 'sectores' => Ajustes::catalogo('sectores')]);
    }

    public function update(Request $request, Entidad $entidad)
    {
        $entidad->update($this->validar($request, $entidad));
        Auditor::log('editar', 'entidades', "Editó el cliente {$entidad->nombre}", $entidad, Auditor::cambios($entidad));

        return redirect()->route('entidades.show', $entidad)->with('ok', 'Cliente actualizado.');
    }

    public function destroy(Entidad $entidad)
    {
        if ($entidad->proyectos()->exists() || $entidad->proyectosComoClienteFinal()->exists() || $entidad->equipos()->exists()) {
            return back()->withErrors(['general' => 'No se puede eliminar un cliente con proyectos o equipos registrados. Puede desactivarlo.']);
        }

        $entidad->delete();
        Auditor::log('eliminar', 'entidades', "Eliminó el cliente {$entidad->nombre}", $entidad);

        return redirect()->route('entidades.index')->with('ok', 'Cliente eliminado.');
    }

    private function validar(Request $request, ?Entidad $entidad = null): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'ruc' => [...\App\Support\Reglas::ruc(), Rule::unique('entidades')->ignore($entidad?->id)],
            'naturaleza' => ['required', Rule::in(array_keys(Entidad::NATURALEZAS))],
            'sector' => ['required', 'string', 'max:60'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'departamento' => ['nullable', 'string', 'max:60'],
            'contacto_nombre' => ['nullable', 'string', 'max:255'],
            'contacto_cargo' => ['nullable', 'string', 'max:255'],
            'contacto_telefono' => \App\Support\Reglas::telefono(),
            'contacto_email' => ['nullable', 'email', 'max:255'],
            'correos_alertas' => ['nullable', 'string', 'max:255', function ($attr, $valor, $fail) {
                foreach (preg_split('/[\s,;]+/', (string) $valor, -1, PREG_SPLIT_NO_EMPTY) as $correo) {
                    if (! filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                        $fail("«{$correo}» no es un correo válido.");
                    }
                }
            }],
        ], [], ['correos_alertas' => 'correos para avisos']);

        return [...$datos, 'activo' => $request->boolean('activo')];
    }
}
