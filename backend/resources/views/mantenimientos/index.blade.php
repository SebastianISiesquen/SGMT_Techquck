<x-layouts.app title="Mantenimientos">
    <x-page-header title="Mantenimientos programados" :subtitle="$soloMias ? 'Sus visitas de mantenimiento preventivo asignadas.' : 'Agenda de visitas preventivas de todos los planes de mantenimiento.'">
        <x-slot:actions>
            @can('mantenimientos.gestionar')
                <a href="{{ route('planes.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Programar mantenimientos</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Atrasados" :value="$resumen['atrasados']" icon="alert" tone="red" :href="route('mantenimientos.index', ['estado' => 'vencido'])" />
        <x-stat label="Próximos 7 días" :value="$resumen['semana']" icon="clock" tone="amber" :href="route('mantenimientos.index', ['estado' => 'proximo'])" />
        <x-stat label="Próximos 30 días" :value="$resumen['mes']" icon="calendar" tone="brand" :href="route('mantenimientos.index', ['estado' => 'proximo'])" />
        <x-stat label="Realizados este año" :value="$resumen['realizados']" icon="check-circle" tone="green" :href="route('mantenimientos.index', ['estado' => 'realizado'])" />
    </div>

    <div class="card">
        <form class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-6">
            <x-select name="estado" :options="['pendiente' => 'Pendientes', 'vencido' => 'Atrasados', 'proximo' => 'Próximos 30 días', 'realizado' => 'Realizados', 'cancelado' => 'Cancelados', 'todos' => 'Todos']" :value="$estado" :placeholder="false" />
            <x-select name="entidad_id" :options="$entidades" :value="request('entidad_id')" placeholder="Todos los clientes" class="lg:col-span-2" />
            @unless ($soloMias)
                <x-select name="tecnico_id" :options="$tecnicos" :value="request('tecnico_id')" placeholder="Todos los técnicos" />
            @endunless
            <x-input name="desde" type="date" :value="request('desde')" />
            <div class="flex gap-2">
                <x-input name="hasta" type="date" :value="request('hasta')" class="flex-1" />
                <button class="btn btn-secondary" title="Filtrar"><x-icon name="search" class="size-4" /></button>
            </div>
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Fecha</th><th>Visita</th><th>Cliente / proyecto</th><th>Técnico</th><th>Estado</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($mantenimientos as $m)
                    <tr>
                        <td class="whitespace-nowrap tabular-nums"><span class="font-medium text-slate-800">{{ $m->fecha_programada->format('d/m/Y') }}</span>
                            <p class="text-xs text-slate-500">{{ $m->estado === 'programado' ? ($m->diasRestantes() >= 0 ? 'en '.$m->diasRestantes().' día(s)' : 'hace '.abs($m->diasRestantes()).' día(s)') : ($m->fecha_ejecucion ? 'hecho el '.$m->fecha_ejecucion->format('d/m/Y') : '') }}</p></td>
                        <td><a href="{{ route('planes.show', $m->plan) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $m->etiqueta() }}</a>
                            <p class="text-xs text-slate-500">{{ $m->plan->nombre }}</p></td>
                        <td class="max-w-xs"><p class="truncate">{{ $m->plan->proyecto->lugarDeServicio()->nombre }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $m->plan->proyecto->codigo }} · {{ $m->plan->proyecto->nombre }}</p></td>
                        <td>{{ $m->tecnico?->name ?? '—' }}</td>
                        <td><x-badge :color="$m->estadoColor()">{{ $m->estadoLabel() }}</x-badge></td>
                        <td class="text-right whitespace-nowrap">
                            @if ($m->hojaServicio)
                                <a href="{{ route('hojas.show', $m->hojaServicio) }}" class="btn btn-ghost btn-sm" title="Hoja de servicio"><x-icon name="clipboard" class="size-4" /> {{ $m->hojaServicio->numero }}</a>
                            @elseif ($m->estado === 'programado')
                                @can('hojas.registrar')
                                    <a href="{{ route('hojas.create', ['mantenimiento_id' => $m->id]) }}" class="btn btn-secondary btn-sm"><x-icon name="clipboard" class="size-4" /> Registrar hoja</a>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty icon="calendar" text="No hay visitas de mantenimiento con estos filtros." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($mantenimientos->hasPages())<div class="border-t border-slate-100 p-4">{{ $mantenimientos->links() }}</div>@endif
    </div>
</x-layouts.app>
