<x-layouts.app title="Checklists de mantenimiento">
    <x-page-header title="Checklists de mantenimiento"
                   subtitle="Lo que el técnico debe revisar en cada tipo de equipo. En la hoja de servicio marca cada punto como OK, observado o falla, y lo observado queda en el historial del equipo." />

    <form method="POST" action="{{ route('checklists.update') }}" class="space-y-6">
        @csrf @method('PUT')
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($categorias as $categoria)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ $categoria }}</h3>
                        @php($n = count(array_filter(preg_split('/\r\n|\r|\n/', (string) ($guardados[$categoria] ?? '')))))
                        <span class="text-xs text-slate-500">{{ $n ? $n.' punto(s)' : 'Sin checklist' }}</span>
                    </div>
                    <div class="card-body">
                        <textarea name="checklists[{{ $categoria }}]" rows="7" class="input text-sm" placeholder="Un punto de control por línea">{{ old('checklists.'.$categoria, $guardados[$categoria] ?? '') }}</textarea>
                    </div>
                </div>
            @endforeach
        </div>
        <p class="help">Las categorías salen de Configuración → Categorías de equipos. Deje vacío el cuadro de una categoría para no usar checklist en ella.</p>
        <div class="flex justify-end"><button class="btn btn-primary">Guardar checklists</button></div>
    </form>
</x-layouts.app>
