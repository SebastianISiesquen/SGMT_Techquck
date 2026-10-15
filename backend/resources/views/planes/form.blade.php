<x-layouts.app :title="$plan->exists ? 'Editar plan' : 'Programar mantenimientos'">
    <x-page-header :title="$plan->exists ? 'Editar plan de mantenimiento' : 'Programar mantenimientos preventivos'"
                   subtitle="Ej.: 4 mantenimientos al año = 4 visitas cada 3 meses. El sistema crea las visitas y avisa cuando se acercan."
                   :back="$plan->exists ? route('planes.show', $plan) : url()->previous()" />

    <form method="POST" action="{{ $plan->exists ? route('planes.update', $plan) : route('planes.store') }}" class="card"
          x-data="{ proyecto: '{{ old('proyecto_id', $plan->proyecto_id) }}', garantia: '{{ old('garantia_id', $plan->garantia_id) }}', garantias: @js($garantias),
                    inicio: '{{ old('fecha_inicio', $plan->fecha_inicio?->format('Y-m-d')) }}', frecuencia: {{ (int) old('frecuencia_meses', $plan->frecuencia_meses) }}, total: {{ (int) old('total_visitas', $plan->total_visitas) }},
                    get fechas() {
                        if (!this.inicio || !this.frecuencia || !this.total) return [];
                        const [a, m, d] = this.inicio.split('-').map(Number);
                        return Array.from({ length: Math.min(this.total, 60) }, (_, i) => {
                            const f = new Date(a, m - 1 + i * this.frecuencia, 1);
                            f.setDate(Math.min(d, new Date(f.getFullYear(), f.getMonth() + 1, 0).getDate()));
                            return f.toLocaleDateString('es-PE');
                        });
                    } }">
        @csrf
        @if ($plan->exists) @method('PUT') @endif
        <div class="card-body space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label" for="f_proyecto_id">Proyecto o servicio <span class="text-red-500">*</span></label>
                    <select id="f_proyecto_id" name="proyecto_id" x-model="proyecto" @change="garantia = ''" required class="input">
                        <option value="">Seleccione…</option>
                        @foreach ($proyectos as $id => $texto)<option value="{{ $id }}">{{ $texto }}</option>@endforeach
                    </select>
                    @error('proyecto_id')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="f_garantia_id">Garantía o contrato asociado</label>
                    <select id="f_garantia_id" name="garantia_id" x-model="garantia" class="input" :disabled="!proyecto">
                        <option value="">— Ninguna —</option>
                        <template x-for="g in garantias.filter(g => String(g.proyecto_id) === String(proyecto))" :key="g.id">
                            <option :value="g.id" x-text="g.texto" :selected="String(g.id) === String(garantia)"></option>
                        </template>
                    </select>
                </div>
                <x-input name="nombre" label="Nombre del plan" :value="$plan->nombre" required />
                <div>
                    <label class="label" for="f_total">N° de visitas <span class="text-red-500">*</span></label>
                    <input id="f_total" type="number" name="total_visitas" min="1" max="60" x-model.number="total" required class="input">
                    @error('total_visitas')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="f_frecuencia">Cada cuántos meses <span class="text-red-500">*</span></label>
                    <select id="f_frecuencia" name="frecuencia_meses" x-model.number="frecuencia" class="input">
                        @foreach ([1 => 'Mensual', 2 => 'Cada 2 meses', 3 => 'Trimestral (cada 3 meses)', 4 => 'Cada 4 meses', 6 => 'Semestral', 12 => 'Anual'] as $m => $t)
                            <option value="{{ $m }}">{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="f_inicio">Fecha de la primera visita <span class="text-red-500">*</span></label>
                    <input id="f_inicio" type="date" name="fecha_inicio" x-model="inicio" required class="input">
                    @error('fecha_inicio')<p class="error">{{ $message }}</p>@enderror
                </div>
                <x-select name="tecnico_id" label="Técnico asignado" :options="$tecnicos" :value="$plan->tecnico_id" placeholder="Asignar después" />
                <x-textarea name="alcance" label="Alcance del mantenimiento" :value="$plan->alcance" rows="3" class="sm:col-span-2"
                            placeholder="Limpieza de cámaras y gabinetes, revisión de NVR, ajuste de conectores, prueba de UPS…" />
            </div>

            <div class="rounded-lg bg-slate-50 p-4">
                <p class="text-sm font-medium text-slate-700">Visitas que se programarán</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    <template x-for="(f, i) in fechas" :key="i">
                        <span class="rounded-full bg-white px-2.5 py-0.5 text-xs text-slate-700 ring-1 ring-slate-200" x-text="(i + 1) + ' de ' + total + ' · ' + f"></span>
                    </template>
                </div>
                @if ($plan->exists)<p class="help mt-2">Las visitas realizadas no cambian. Si modifica la primera fecha o la frecuencia, las visitas pendientes se recalculan.</p>@endif
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <a href="{{ url()->previous() }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-layouts.app>
