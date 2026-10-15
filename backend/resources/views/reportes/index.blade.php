<x-layouts.app title="Reportes">
    <x-page-header title="Reportes" subtitle="Genere reportes consolidados en PDF o Excel aplicando filtros." />

    @php
        $config = [
            'proyectos' => ['icon' => 'folder', 'desc' => 'Proyectos y servicios con cliente, cliente final, orden de compra e incidencias.', 'estados' => $estadosProyecto, 'fecha' => 'Fecha de entrega', 'tecnico' => false, 'prioridad' => false],
            'garantias' => ['icon' => 'shield', 'desc' => 'Vigencia, días restantes y estado de cada garantía.', 'estados' => $estadosGarantia, 'fecha' => 'Fecha de vencimiento', 'tecnico' => false, 'prioridad' => false],
            'equipos' => ['icon' => 'monitor', 'desc' => 'Inventario por cliente: serie, ubicación, responsable, fallas y último mantenimiento.', 'estados' => $estadosEquipo, 'fecha' => 'Fecha de instalación', 'tecnico' => false, 'prioridad' => false, 'categoria' => true],
            'incidencias' => ['icon' => 'alert', 'desc' => 'Incidencias por estado, prioridad, técnico y tiempos de atención.', 'estados' => $estadosIncidencia, 'fecha' => 'Fecha de reporte', 'tecnico' => true, 'prioridad' => true],
            'atenciones' => ['icon' => 'wrench', 'desc' => 'Atenciones técnicas realizadas, resultados y evidencias.', 'estados' => \App\Enums\ResultadoAtencion::options(), 'fecha' => 'Fecha de atención', 'tecnico' => true, 'prioridad' => false],
            'hojas' => ['icon' => 'clipboard', 'desc' => 'Servicios en campo por técnico, tipo y conformidad.', 'estados' => $estadosHoja, 'fecha' => 'Fecha del servicio', 'tecnico' => true, 'prioridad' => false, 'servicio' => true],
            'mantenimientos' => ['icon' => 'calendar', 'desc' => 'Visitas programadas, realizadas y atrasadas por cliente y técnico.', 'estados' => $estadosMantenimiento, 'fecha' => 'Fecha programada', 'tecnico' => true, 'prioridad' => false],
            'almacen' => ['icon' => 'box', 'desc' => 'Entradas, salidas (consumo por servicio, cliente y técnico) y ajustes del almacén.', 'estados' => $tiposMovimiento, 'fecha' => 'Fecha', 'tecnico' => true, 'prioridad' => false],
            'cotizaciones' => ['icon' => 'receipt', 'desc' => 'Cotizaciones emitidas, montos y estado de aprobación.', 'estados' => $estadosCotizacion, 'fecha' => 'Fecha', 'tecnico' => false, 'prioridad' => false],
        ];
    @endphp

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($tipos as $tipo => $nombre)
            @php($c = $config[$tipo])
            <form class="card" x-data x-ref="form" method="GET" target="_blank">
                <div class="card-header">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><x-icon :name="$c['icon']" class="size-5" /></div>
                        <div><h3 class="card-title">{{ $nombre }}</h3><p class="text-xs text-slate-500">{{ $c['desc'] }}</p></div>
                    </div>
                </div>
                <div class="card-body grid gap-3 sm:grid-cols-2">
                    <x-select name="entidad_id" label="Cliente" :options="$entidades" placeholder="Todos" />
                    <x-select name="estado" :label="$tipo === 'atenciones' ? 'Resultado' : ($tipo === 'almacen' ? 'Movimiento' : 'Estado')" :options="$c['estados']" placeholder="Todos" />
                    @if ($c['prioridad'])<x-select name="prioridad" label="Prioridad" :options="$prioridades" placeholder="Todas" />@endif
                    @if ($c['tecnico'])<x-select name="tecnico_id" label="Técnico" :options="$tecnicos" placeholder="Todos" />@endif
                    @if ($c['servicio'] ?? false)<x-select name="tipo_servicio" label="Tipo de servicio" :options="$tiposServicio" placeholder="Todos" />@endif
                    @if ($c['categoria'] ?? false)<x-select name="categoria" label="Categoría" :options="$categorias" :list="true" placeholder="Todas" />@endif
                    <x-input name="desde" type="date" :label="$c['fecha'].' desde'" />
                    <x-input name="hasta" type="date" label="hasta" />
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-3">
                    <button type="submit" formaction="{{ route('reportes.exportar', [$tipo, 'excel']) }}" class="btn btn-secondary"><x-icon name="sheet" class="size-4 text-emerald-600" /> Excel</button>
                    <button type="submit" formaction="{{ route('reportes.exportar', [$tipo, 'pdf']) }}" class="btn btn-primary"><x-icon name="pdf" class="size-4" /> PDF</button>
                </div>
            </form>
        @endforeach
    </div>
</x-layouts.app>
