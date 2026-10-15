<x-layouts.app title="Proyectos y servicios">
    <x-page-header title="Proyectos y servicios" :subtitle="'Implementaciones con garantía y servicios de mantenimiento o soporte (alcance de '.\App\Support\Ajustes::int('anios_historial').' años).'">
        <x-slot:actions>
            @can('proyectos.gestionar')
                <a href="{{ route('proyectos.create', ['clase' => 'servicio']) }}" class="btn btn-secondary"><x-icon name="wrench" class="size-4" /> Nuevo servicio</a>
                <a href="{{ route('proyectos.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Nuevo proyecto</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <form class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-7">
            <x-input name="buscar" :value="request('buscar')" placeholder="Nombre, código, contrato u OC…" class="lg:col-span-2" />
            <x-select name="entidad_id" :options="$entidades" :value="request('entidad_id')" placeholder="Todos los clientes" />
            <x-select name="clase" :options="['implementacion' => 'Implementaciones', 'servicio' => 'Servicios']" :value="request('clase')" placeholder="Proyectos y servicios" />
            <x-select name="tipo" :options="$tipos" :list="true" :value="request('tipo')" placeholder="Todos los rubros" />
            <x-select name="estado" :options="\App\Models\Proyecto::ESTADOS" :value="request('estado')" placeholder="En curso" />
            <div class="flex gap-2">
                <x-input name="anio" type="number" :value="request('anio')" placeholder="Año" min="2000" max="2100" class="flex-1" />
                <button class="btn btn-secondary" title="Filtrar"><x-icon name="search" class="size-4" /></button>
            </div>
        </form>
        @unless (request('estado'))
            <p class="border-b border-slate-100 bg-slate-50 px-4 py-2 text-xs text-slate-500">Se muestran los proyectos y servicios en curso. Para ver los terminados elija el estado «Finalizado».</p>
        @endunless
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Proyecto / servicio</th><th>Cliente</th><th>Rubro</th><th>Entrega / inicio</th><th class="text-center">Garantías</th><th class="text-center">Equipos</th><th class="text-center">Inc. abiertas</th><th>Estado</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($proyectos as $p)
                    <tr>
                        <td class="max-w-sm"><a href="{{ route('proyectos.show', $p) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $p->nombre }}</a>
                            <p class="text-xs text-slate-500">{{ $p->codigo }}{{ $p->orden_compra ? ' · OC '.$p->orden_compra : '' }}{{ $p->numero_contrato ? ' · '.$p->numero_contrato : '' }}</p></td>
                        <td>{{ $p->entidad->nombre }}
                            @if ($p->clienteFinal)<p class="text-xs text-slate-500">Cliente final: {{ $p->clienteFinal->nombre }}</p>@endif</td>
                        <td>{{ $p->tipo }}<p class="text-xs"><x-badge :color="$p->esServicio() ? 'violet' : 'blue'" :dot="false">{{ $p->esServicio() ? 'Servicio' : 'Implementación' }}</x-badge></p></td>
                        <td class="whitespace-nowrap tabular-nums">{{ $p->fecha_entrega->format('d/m/Y') }}
                            @if ($p->fecha_fin)<p class="text-xs text-slate-500">hasta {{ $p->fecha_fin->format('d/m/Y') }}</p>@endif</td>
                        <td class="text-center tabular-nums">{{ $p->garantias_count }}</td>
                        <td class="text-center tabular-nums">{{ $p->equipos_count }}</td>
                        <td class="text-center tabular-nums">
                            @if ($p->incidencias_abiertas_count)<x-badge color="amber">{{ $p->incidencias_abiertas_count }}</x-badge>@else 0 @endif
                        </td>
                        <td><x-badge :color="$p->estado === 'operativo' ? 'green' : ($p->estado === 'suspendido' ? 'amber' : 'slate')">{{ $p->estadoLabel() }}</x-badge></td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('proyectos.show', $p) }}" class="btn btn-ghost btn-sm" title="Ver"><x-icon name="eye" class="size-4" /></a>
                            @can('proyectos.gestionar')
                                <a href="{{ route('proyectos.edit', $p) }}" class="btn btn-ghost btn-sm" title="Editar"><x-icon name="edit" class="size-4" /></a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-empty icon="folder" text="No se encontraron proyectos ni servicios." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($proyectos->hasPages())<div class="border-t border-slate-100 p-4">{{ $proyectos->links() }}</div>@endif
    </div>
</x-layouts.app>
