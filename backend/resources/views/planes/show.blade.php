<x-layouts.app :title="$plan->nombre">
    <x-page-header :title="$plan->nombre" :subtitle="$plan->proyecto->codigo.' · '.$plan->proyecto->nombre.' · '.$plan->proyecto->clientes()" :back="route('mantenimientos.index')">
        <x-slot:actions>
            @can('mantenimientos.gestionar')
                <a href="{{ route('planes.edit', $plan) }}" class="btn btn-secondary"><x-icon name="edit" class="size-4" /> Editar plan</a>
                <form method="POST" action="{{ route('planes.destroy', $plan) }}" data-confirmar="¿Retirar este plan? Las visitas pendientes se cancelarán." data-confirmar-tipo="peligro" data-confirmar-boton="Sí, retirar" data-confirmar-detalle="Esta acción no se puede deshacer.">
                    @csrf @method('DELETE')
                    <button class="btn btn-secondary text-red-600"><x-icon name="trash" class="size-4" /></button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header"><h3 class="card-title">Visitas ({{ $plan->realizadas() }} de {{ $plan->total_visitas }} realizadas)</h3></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Visita</th><th>Fecha programada</th><th>Técnico</th><th>Estado</th><th>Hoja de servicio</th>@can('mantenimientos.gestionar')<th></th>@endcan</tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($plan->mantenimientos as $m)
                        <tr x-data="{ editar: false }">
                            <td class="font-medium text-slate-800">{{ $m->numero }} de {{ $plan->total_visitas }}
                                @if ($m->observacion)<p class="text-xs font-normal text-slate-500">{{ $m->observacion }}</p>@endif</td>
                            <td class="tabular-nums">{{ $m->fecha_programada->format('d/m/Y') }}
                                @if ($m->fecha_ejecucion)<p class="text-xs text-slate-500">Hecho el {{ $m->fecha_ejecucion->format('d/m/Y') }}</p>@endif</td>
                            <td>{{ $m->tecnico?->name ?? '—' }}</td>
                            <td><x-badge :color="$m->estadoColor()">{{ $m->estadoLabel() }}</x-badge></td>
                            <td>
                                @if ($m->hojaServicio)
                                    <a href="{{ route('hojas.show', $m->hojaServicio) }}" class="text-brand-600 hover:underline">{{ $m->hojaServicio->numero }}</a>
                                @elseif ($m->estado === 'programado')
                                    @can('hojas.registrar')<a href="{{ route('hojas.create', ['mantenimiento_id' => $m->id]) }}" class="btn btn-secondary btn-sm">Registrar hoja</a>@endcan
                                @endif
                            </td>
                            @can('mantenimientos.gestionar')
                                <td class="text-right">
                                    @if ($m->estado !== 'realizado')
                                        <button type="button" class="btn btn-ghost btn-sm" @click="editar = !editar" title="Reprogramar"><x-icon name="edit" class="size-4" /></button>
                                        <template x-teleport="body">
                                            <div x-show="editar" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" @keydown.escape.window="editar = false">
                                                <form method="POST" action="{{ route('mantenimientos.update', $m) }}" class="card w-full max-w-md" @click.outside="editar = false">
                                                    @csrf @method('PUT')
                                                    <div class="card-header"><h3 class="card-title">{{ $m->etiqueta() }}</h3></div>
                                                    <div class="card-body space-y-3">
                                                        <x-input name="fecha_programada" type="date" label="Fecha" :value="$m->fecha_programada" required />
                                                        <x-select name="tecnico_id" label="Técnico" :options="$tecnicos" :value="$m->tecnico_id" placeholder="Sin asignar" />
                                                        <x-select name="estado" label="Estado" :options="['programado' => 'Programado', 'cancelado' => 'Cancelado']" :value="$m->estado" :placeholder="false" />
                                                        <x-input name="observacion" label="Observación" :value="$m->observacion" placeholder="Ej. El cliente pidió reprogramar" />
                                                    </div>
                                                    <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-3">
                                                        <button type="button" class="btn btn-secondary" @click="editar = false">Cancelar</button>
                                                        <button class="btn btn-primary">Guardar</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </template>
                                    @endif
                                </td>
                            @endcan
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Datos del plan</h3>
                <x-badge :color="$plan->activo ? 'green' : 'slate'">{{ $plan->activo ? 'Activo' : 'Inactivo' }}</x-badge></div>
            <dl class="card-body dl-grid sm:grid-cols-1">
                <div><dt>Proyecto / servicio</dt><dd><a href="{{ route('proyectos.show', $plan->proyecto) }}" class="text-brand-600 hover:underline">{{ $plan->proyecto->nombre }}</a></dd></div>
                <div><dt>Lugar del servicio</dt><dd>{{ $plan->proyecto->lugarDeServicio()->nombre }}</dd></div>
                <div><dt>Frecuencia</dt><dd>{{ $plan->total_visitas }} visitas, cada {{ $plan->frecuencia_meses }} mes(es)</dd></div>
                <div><dt>Garantía / contrato</dt><dd>@if ($plan->garantia)<a href="{{ route('garantias.show', $plan->garantia) }}" class="text-brand-600 hover:underline">{{ $plan->garantia->etiqueta() }}</a>@else — @endif</dd></div>
                <div><dt>Técnico por defecto</dt><dd>{{ $plan->tecnico?->name ?? '—' }}</dd></div>
                @if ($plan->alcance)<div><dt>Alcance</dt><dd class="whitespace-pre-line">{{ $plan->alcance }}</dd></div>@endif
            </dl>
        </div>
    </div>
</x-layouts.app>
