@props([
    'archivos',
    'tipo' => null,
    'id' => null,
    'puedeSubir' => false,
    'categorias' => null,
    'categoria' => 'documento',
    'titulo' => 'Documentos y evidencias',
])
@php
    $imagenes = $archivos->filter->esImagen();
    $documentos = $archivos->reject->esImagen();
    $opciones = collect(\App\Enums\CategoriaArchivo::options())
        ->when($categorias, fn ($c) => $c->only($categorias));
    $formId = 'subir-'.$tipo.'-'.$id;
@endphp
<div class="card" x-data="{ subir: false }">
    <div class="card-header">
        <h3 class="card-title flex items-center gap-2"><x-icon name="paperclip" class="size-4 text-slate-400" /> {{ $titulo }}
            <span class="text-xs font-normal text-slate-400">({{ $archivos->count() }})</span></h3>
        @if ($puedeSubir && $tipo)
            <button type="button" class="btn btn-secondary btn-sm" @click="subir = !subir">
                <x-icon name="upload" class="size-4" /> Subir archivos
            </button>
        @endif
    </div>

    @if ($puedeSubir && $tipo)
        <form id="{{ $formId }}" x-show="subir" x-cloak method="POST" action="{{ route('archivos.store', [$tipo, $id]) }}" enctype="multipart/form-data"
              class="space-y-3 border-b border-slate-100 bg-slate-50/60 p-5">
            @csrf
            <div class="grid gap-3 sm:grid-cols-3">
                <x-select name="categoria" label="Categoría" :options="$opciones" :value="$categoria" :placeholder="false" required />
                <x-input name="descripcion" label="Descripción (opcional)" class="sm:col-span-2" placeholder="Ej. Foto del equipo reemplazado" />
            </div>
            <div>
                <input type="file" name="archivos[]" multiple required class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100">
                <p class="help">Imágenes, PDF, Word, Excel u otros. Máximo {{ \App\Support\Ajustes::int('max_archivo_mb') }} MB por archivo.</p>
                @error('archivos')<p class="error">{{ $message }}</p>@enderror
                @error('archivos.*')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-ghost btn-sm" @click="subir = false">Cancelar</button>
                <button class="btn btn-primary btn-sm"><x-icon name="upload" class="size-4" /> Subir</button>
            </div>
        </form>
    @endif

    <div class="card-body space-y-4">
        @if ($archivos->isEmpty())
            <p class="text-sm text-slate-500">Sin archivos adjuntos.</p>
        @endif

        @if ($imagenes->isNotEmpty())
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($imagenes as $img)
                    <figure class="group relative overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                        <a href="{{ route('archivos.ver', $img) }}" target="_blank">
                            <img src="{{ route('archivos.ver', $img) }}" alt="{{ $img->descripcion ?: $img->nombre_original }}" loading="lazy" class="aspect-[4/3] w-full object-cover transition group-hover:scale-105">
                        </a>
                        <figcaption class="flex items-center justify-between gap-1 px-2 py-1.5 text-xs">
                            <span class="truncate text-slate-600" title="{{ $img->nombre_original }}">{{ $img->descripcion ?: $img->categoria->label() }}</span>
                            @can('delete', $img)
                                <form method="POST" action="{{ route('archivos.destroy', $img) }}" data-confirmar="¿Eliminar esta imagen?" data-confirmar-tipo="peligro" data-confirmar-boton="Sí, eliminar" data-confirmar-detalle="Esta acción no se puede deshacer.">
                                    @csrf @method('DELETE')
                                    <button class="text-slate-400 hover:text-red-600" title="Eliminar"><x-icon name="trash" class="size-3.5" /></button>
                                </form>
                            @endcan
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        @endif

        @if ($documentos->isNotEmpty())
            <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200">
                @foreach ($documentos as $doc)
                    <li class="flex items-center gap-3 px-3 py-2.5">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $doc->esPdf() ? 'bg-red-50 text-red-600' : (in_array($doc->extension(), ['xls', 'xlsx']) ? 'bg-emerald-50 text-emerald-600' : 'bg-blue-50 text-blue-600') }}">
                            <span class="text-[10px] font-bold uppercase">{{ $doc->extension() }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-800">{{ $doc->nombre_original }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $doc->categoria->label() }} · {{ $doc->tamanoLegible() }} · {{ $doc->created_at->format('d/m/Y H:i') }}
                                @if ($doc->autor) · {{ $doc->autor->name }} @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            @if ($doc->esPdf())
                                <a href="{{ route('archivos.ver', $doc) }}" target="_blank" class="btn btn-ghost btn-sm" title="Ver"><x-icon name="eye" class="size-4" /></a>
                            @endif
                            <a href="{{ route('archivos.descargar', $doc) }}" class="btn btn-ghost btn-sm" title="Descargar"><x-icon name="download" class="size-4" /></a>
                            @can('delete', $doc)
                                <form method="POST" action="{{ route('archivos.destroy', $doc) }}" data-confirmar="¿Eliminar este archivo?" data-confirmar-tipo="peligro" data-confirmar-boton="Sí, eliminar" data-confirmar-detalle="Esta acción no se puede deshacer.">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-sm text-slate-400 hover:text-red-600" title="Eliminar"><x-icon name="trash" class="size-4" /></button>
                                </form>
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
