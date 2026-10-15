<x-layouts.app :title="$cotizacion->numero">
    <x-page-header :title="'Cotización '.$cotizacion->numero" :subtitle="$cotizacion->entidad->nombre.' · '.$cotizacion->fecha->format('d/m/Y')" :back="route('cotizaciones.index')">
        <x-slot:actions>
            <a href="{{ route('cotizaciones.pdf', $cotizacion) }}" target="_blank" class="btn btn-secondary"><x-icon name="pdf" class="size-4" /> PDF</a>
            @can('cotizaciones.gestionar')
                <a href="{{ route('cotizaciones.edit', $cotizacion) }}" class="btn btn-secondary"><x-icon name="edit" class="size-4" /> Editar</a>
                <form method="POST" action="{{ route('cotizaciones.destroy', $cotizacion) }}" data-confirmar="¿Eliminar la cotización?" data-confirmar-tipo="peligro" data-confirmar-boton="Sí, eliminar" data-confirmar-detalle="Esta acción no se puede deshacer.">
                    @csrf @method('DELETE')
                    <button class="btn btn-secondary text-red-600"><x-icon name="trash" class="size-4" /></button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($cotizacion->estado === 'aprobada')
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-5">
            <p class="flex items-center gap-2 font-semibold text-emerald-900"><x-icon name="check-circle" class="size-5" /> Cotización aprobada: siguiente paso</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @if ($cotizacion->incidencia)
                    <a href="{{ route('incidencias.show', $cotizacion->incidencia) }}" class="btn btn-primary"><x-icon name="alert" class="size-4" /> Continuar la incidencia {{ $cotizacion->incidencia->codigo }}</a>
                @else
                    @can('incidencias.gestionar')
                        @if ($cotizacion->proyecto_id)
                            <a href="{{ route('incidencias.create', ['cotizacion_id' => $cotizacion->id]) }}" class="btn btn-primary"><x-icon name="alert" class="size-4" /> Crear orden de trabajo</a>
                        @endif
                    @endcan
                    @can('proyectos.gestionar')
                        <a href="{{ route('proyectos.create', ['cotizacion_id' => $cotizacion->id, 'clase' => 'servicio']) }}" class="btn btn-secondary"><x-icon name="wrench" class="size-4" /> Crear contrato de servicio</a>
                        <a href="{{ route('proyectos.create', ['cotizacion_id' => $cotizacion->id]) }}" class="btn btn-secondary"><x-icon name="folder" class="size-4" /> Crear proyecto de implementación</a>
                    @endcan
                @endif
            </div>
            <p class="mt-2 text-xs text-emerald-800">El trabajo que cree queda vinculado a esta cotización.</p>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card">
                <div class="card-header"><h3 class="card-title">{{ $cotizacion->asunto }}</h3><x-badge :color="$cotizacion->estadoColor()">{{ $cotizacion->estadoLabel() }}</x-badge></div>
                @if ($cotizacion->diagnostico)<div class="card-body whitespace-pre-line text-sm text-slate-700">{{ $cotizacion->diagnostico }}</div>@endif
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Descripción</th><th class="text-right">Cant.</th><th>Unidad</th><th class="text-right">P. unitario</th><th class="text-right">Importe</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                        @foreach ($cotizacion->items as $i)
                            <tr><td>{{ $i->descripcion }}</td><td class="text-right tabular-nums">{{ rtrim(rtrim(number_format($i->cantidad, 2), '0'), '.') }}</td><td>{{ $i->unidad }}</td>
                                <td class="text-right tabular-nums">{{ $cotizacion->monto($i->precio_unitario) }}</td><td class="text-right tabular-nums">{{ $cotizacion->monto($i->importe()) }}</td></tr>
                        @endforeach
                        </tbody>
                        <tfoot class="text-sm">
                            <tr><td colspan="4" class="text-right text-slate-500">Subtotal</td><td class="text-right tabular-nums">{{ $cotizacion->monto($cotizacion->subtotal) }}</td></tr>
                            <tr><td colspan="4" class="text-right text-slate-500">IGV</td><td class="text-right tabular-nums">{{ $cotizacion->monto($cotizacion->igv) }}</td></tr>
                            <tr><td colspan="4" class="text-right font-semibold">Total</td><td class="text-right font-semibold tabular-nums">{{ $cotizacion->monto($cotizacion->total) }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            @if ($cotizacion->condiciones)
                <div class="card"><div class="card-header"><h3 class="card-title">Condiciones</h3></div>
                    <div class="card-body whitespace-pre-line text-sm text-slate-700">{{ $cotizacion->condiciones }}</div></div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Datos</h3></div>
                <dl class="card-body dl-grid sm:grid-cols-1">
                    <div><dt>Cliente</dt><dd><a href="{{ route('entidades.show', $cotizacion->entidad) }}" class="text-brand-600 hover:underline">{{ $cotizacion->entidad->nombre }}</a></dd></div>
                    <div><dt>Válida hasta</dt><dd>{{ $cotizacion->venceEl()->format('d/m/Y') }} ({{ $cotizacion->validez_dias }} días)</dd></div>
                    @if ($cotizacion->incidencia)<div><dt>Incidencia</dt><dd><a href="{{ route('incidencias.show', $cotizacion->incidencia) }}" class="text-brand-600 hover:underline">{{ $cotizacion->incidencia->codigo }}</a></dd></div>@endif
                    @if ($cotizacion->equipo)<div><dt>Equipo</dt><dd><a href="{{ route('equipos.show', $cotizacion->equipo) }}" class="text-brand-600 hover:underline">{{ $cotizacion->equipo->codigo }} · {{ $cotizacion->equipo->nombre() }}</a></dd></div>@endif
                    <div><dt>Elaborada por</dt><dd>{{ $cotizacion->autor?->name ?? '—' }}</dd></div>
                </dl>
            </div>
            @can('cotizaciones.gestionar')
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Seguimiento</h3></div>
                    <div class="card-body flex flex-wrap gap-2">
                        @foreach (\App\Models\Cotizacion::ESTADOS as $k => $v)
                            @if ($k !== $cotizacion->estado)
                                <form method="POST" action="{{ route('cotizaciones.estado', $cotizacion) }}"
                                      data-confirmar="¿Marcar la cotización como {{ mb_strtolower($v) }}?" data-confirmar-tipo="{{ in_array($k, ['rechazada', 'vencida']) ? 'advertencia' : 'info' }}" data-confirmar-boton="Marcar {{ mb_strtolower($v) }}">
                                    @csrf <input type="hidden" name="estado" value="{{ $k }}">
                                    <button class="btn btn-secondary btn-sm">Marcar {{ mb_strtolower($v) }}</button>
                                </form>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endcan
        </div>
    </div>
</x-layouts.app>
