<x-layouts.app title="Dashboard">
    <x-page-header title="Dashboard" :subtitle="'Situación de garantías e incidencias al '.now()->translatedFormat('d \d\e F \d\e Y')">
        <x-slot:actions>
            @can('incidencias.gestionar')
                <a href="{{ route('incidencias.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Nueva incidencia</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Estado de garantías: estado = ícono + etiqueta + color --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat label="Garantías vigentes" :value="$garantias['vigente']" icon="check-circle" tone="green" :href="route('garantias.index', ['estado' => 'vigente'])" />
        <x-stat label="Próximas a vencer" :value="$garantias['por_vencer']" icon="clock" tone="amber" :href="route('garantias.index', ['estado' => 'por_vencer'])"
                :hint="'En los próximos '.\App\Support\Ajustes::int('dias_alerta_garantia').' días'" />
        <x-stat label="Garantías vencidas" :value="$garantias['vencida']" icon="x-circle" tone="red" :href="route('garantias.index', ['estado' => 'vencida'])" />
    </div>

    <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-stat label="Clientes activos" :value="$kpis['entidades']" icon="building" tone="slate" :href="route('entidades.index')" />
        <x-stat label="Proyectos y servicios" :value="$kpis['proyectos']" icon="folder" tone="brand" :href="route('proyectos.index')" />
        <x-stat label="Incidencias abiertas" :value="$kpis['incidencias_abiertas']" icon="alert" tone="amber" :href="route('incidencias.index', ['estado' => 'abiertas'])" />
        <x-stat label="Resueltas por cerrar" :value="$kpis['por_cerrar']" icon="check" tone="violet" :href="route('incidencias.index', ['estado' => 'resuelta'])" />
        <x-stat label="Tiempo prom. de atención" :value="$kpis['horas_promedio'] !== null ? $kpis['horas_promedio'].' h' : '—'" icon="clock" tone="slate" hint="Reporte → resolución" />
    </div>

    <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Equipos registrados" :value="$operacion['equipos']" icon="monitor" tone="brand" :href="route('equipos.index')"
                :hint="$operacion['equipos_falla'].' con falla o en reparación'" />
        <x-stat label="Mantenimientos (30 días)" :value="$operacion['mant_mes']" icon="calendar" tone="violet" :href="route('mantenimientos.index', ['estado' => 'proximo'])" />
        <x-stat label="Mantenimientos atrasados" :value="$operacion['mant_atrasados']" icon="alert" :tone="$operacion['mant_atrasados'] ? 'red' : 'slate'" :href="route('mantenimientos.index', ['estado' => 'vencido'])" />
        <x-stat label="Cotizaciones por responder" :value="$operacion['cotizaciones_enviadas']" icon="receipt" tone="amber" :href="route('cotizaciones.index', ['estado' => 'enviada'])" hint="Enviadas al cliente" />
    </div>

    @if ($kpis['sla_vencido'])
        <a href="{{ route('incidencias.index', ['estado' => 'abiertas']) }}" class="mt-4 flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 hover:bg-red-100">
            <x-icon name="alert" class="size-5 shrink-0" />
            <span><strong>{{ $kpis['sla_vencido'] }}</strong> incidencia(s) abiertas superaron su fecha límite de atención.</span>
        </a>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Incidencias reportadas vs cerradas (12 meses)</h3></div>
            <div class="card-body"><div class="h-64"><canvas id="chartTendencia" role="img" aria-label="Incidencias reportadas y cerradas por mes"></canvas></div></div>
        </div>
        <div class="card">
            <div class="card-header"><h3 class="card-title">Incidencias por estado</h3></div>
            <div class="card-body"><div class="h-64"><canvas id="chartEstados" role="img" aria-label="Cantidad de incidencias por estado"></canvas></div></div>
        </div>
        <div class="card">
            <div class="card-header"><h3 class="card-title">Garantías que vencen en los próximos 12 meses</h3></div>
            <div class="card-body"><div class="h-64"><canvas id="chartVencimientos" role="img" aria-label="Garantías por mes de vencimiento"></canvas></div></div>
        </div>
        <div class="card">
            <div class="card-header"><h3 class="card-title">Incidencias abiertas por cliente</h3></div>
            <div class="card-body">
                @if (count($chartEntidades['data']))
                    <div class="h-64"><canvas id="chartEntidades" role="img" aria-label="Incidencias abiertas por cliente"></canvas></div>
                @else
                    <x-empty icon="check-circle" text="No hay incidencias abiertas." />
                @endif
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title flex items-center gap-2"><x-icon name="clock" class="size-4 text-amber-500" /> Garantías próximas a vencer</h3>
                <a href="{{ route('garantias.index', ['estado' => 'por_vencer']) }}" class="text-xs font-medium text-brand-600 hover:underline">Ver todas</a>
            </div>
            @forelse ($proximasVencer as $g)
                <a href="{{ route('garantias.show', $g) }}" class="flex items-center gap-4 border-b border-slate-100 px-5 py-3 last:border-0 hover:bg-slate-50">
                    <div class="w-14 shrink-0 text-center">
                        <p class="text-lg font-semibold tabular-nums {{ $g->diasRestantes() <= 15 ? 'text-red-600' : 'text-amber-600' }}">{{ $g->diasRestantes() }}</p>
                        <p class="text-[10px] uppercase text-slate-500">días</p>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-800">{{ $g->proyecto->nombre }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $g->tipo }} · {{ $g->proyecto->entidad->nombre }}</p>
                    </div>
                    <span class="shrink-0 text-xs text-slate-500">{{ $g->fecha_vencimiento->format('d/m/Y') }}</span>
                </a>
            @empty
                <x-empty icon="shield" text="No hay garantías próximas a vencer." />
            @endforelse
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title flex items-center gap-2"><x-icon name="alert" class="size-4 text-red-500" /> Incidencias pendientes por prioridad</h3>
                <a href="{{ route('incidencias.index', ['estado' => 'abiertas']) }}" class="text-xs font-medium text-brand-600 hover:underline">Ver todas</a>
            </div>
            @forelse ($incidenciasCriticas as $i)
                <a href="{{ route('incidencias.show', $i) }}" class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 last:border-0 hover:bg-slate-50">
                    <x-badge :color="$i->prioridad->color()">{{ $i->prioridad->label() }}</x-badge>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-800">{{ $i->codigo }} · {{ $i->titulo }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $i->proyecto->entidad->nombre }} · {{ $i->tecnico?->name ?? 'Sin asignar' }}</p>
                    </div>
                    <x-badge :color="$i->estado->color()">{{ $i->estado->label() }}</x-badge>
                </a>
            @empty
                <x-empty icon="check-circle" text="No hay incidencias pendientes." />
            @endforelse
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title flex items-center gap-2"><x-icon name="calendar" class="size-4 text-violet-500" /> Próximos mantenimientos</h3>
                <a href="{{ route('mantenimientos.index') }}" class="text-xs font-medium text-brand-600 hover:underline">Ver agenda</a>
            </div>
            @forelse ($proximosMantenimientos as $m)
                <a href="{{ route('planes.show', $m->plan) }}" class="flex items-center gap-4 border-b border-slate-100 px-5 py-3 last:border-0 hover:bg-slate-50">
                    <div class="w-14 shrink-0 text-center">
                        <p class="text-sm font-semibold tabular-nums {{ $m->vencido() ? 'text-red-600' : 'text-slate-800' }}">{{ $m->fecha_programada->format('d/m') }}</p>
                        <p class="text-[10px] uppercase text-slate-500">{{ $m->vencido() ? 'atrasado' : 'en '.$m->diasRestantes().' d' }}</p>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-800">{{ $m->etiqueta() }} · {{ $m->plan->nombre }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $m->plan->proyecto->lugarDeServicio()->nombre }} · {{ $m->tecnico?->name ?? 'Sin técnico' }}</p>
                    </div>
                </a>
            @empty
                <x-empty icon="calendar" text="No hay mantenimientos en los próximos 30 días." />
            @endforelse
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title flex items-center gap-2"><x-icon name="monitor" class="size-4 text-red-500" /> Equipos con fallas repetidas (6 meses)</h3>
                <a href="{{ route('equipos.index', ['estado' => 'con_falla']) }}" class="text-xs font-medium text-brand-600 hover:underline">Equipos con falla</a>
            </div>
            @forelse ($fallasRecurrentes as $e)
                <a href="{{ route('equipos.show', $e) }}" class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 last:border-0 hover:bg-slate-50">
                    <x-badge color="red">{{ $e->fallas }} fallas</x-badge>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-800">{{ $e->codigo }} · {{ trim($e->categoria.' '.$e->marca.' '.$e->modelo) }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $e->entidad->nombre }}{{ $e->ubicacion ? ' · '.$e->ubicacion : '' }}</p>
                    </div>
                </a>
            @empty
                <x-empty icon="check-circle" text="Ningún equipo tuvo fallas repetidas en los últimos 6 meses." />
            @endforelse
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Paleta categórica validada (azul, naranja) y rampa secuencial azul; grilla recesiva.
            const azul = '#2a78d6', naranja = '#eb6834', grid = '#e7e5e4';
            const ejes = {
                x: { grid: { display: false }, border: { color: '#d6d3d1' } },
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: grid }, border: { display: false } },
            };
            const barra = { borderRadius: 4, borderSkipped: 'bottom', maxBarThickness: 36 };

            new Chart(document.getElementById('chartTendencia'), {
                type: 'line',
                data: {
                    labels: @json($chartTendencia['labels']),
                    datasets: [
                        { label: 'Reportadas', data: @json($chartTendencia['reportadas']), borderColor: azul, backgroundColor: azul, borderWidth: 2, pointRadius: 3, pointHoverRadius: 5, tension: .3 },
                        { label: 'Cerradas', data: @json($chartTendencia['cerradas']), borderColor: naranja, backgroundColor: naranja, borderWidth: 2, pointRadius: 3, pointHoverRadius: 5, tension: .3 },
                    ],
                },
                options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, scales: ejes, plugins: { legend: { position: 'top', align: 'end' } } },
            });

            new Chart(document.getElementById('chartEstados'), {
                type: 'bar',
                data: { labels: @json($chartEstados['labels']), datasets: [{ label: 'Incidencias', data: @json($chartEstados['data']), backgroundColor: azul, ...barra }] },
                options: { maintainAspectRatio: false, scales: ejes, plugins: { legend: { display: false } } },
            });

            new Chart(document.getElementById('chartVencimientos'), {
                type: 'bar',
                data: { labels: @json($chartVencimientos['labels']), datasets: [{ label: 'Garantías que vencen', data: @json($chartVencimientos['data']), backgroundColor: '#1c5cab', ...barra }] },
                options: { maintainAspectRatio: false, scales: ejes, plugins: { legend: { display: false } } },
            });

            const ent = document.getElementById('chartEntidades');
            if (ent) new Chart(ent, {
                type: 'bar',
                data: { labels: @json($chartEntidades['labels']), datasets: [{ label: 'Incidencias abiertas', data: @json($chartEntidades['data']), backgroundColor: azul, borderRadius: 4, borderSkipped: 'left', maxBarThickness: 22 }] },
                options: {
                    indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: grid }, border: { display: false } },
                        y: { grid: { display: false }, ticks: { callback(v) { const l = this.getLabelForValue(v); return l.length > 28 ? l.slice(0, 27) + '…' : l; } } },
                    },
                },
            });
        });
    </script>
    @endpush
</x-layouts.app>
