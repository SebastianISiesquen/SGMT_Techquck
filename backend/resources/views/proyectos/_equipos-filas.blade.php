{{-- Filas para registrar los equipos instalados en el proyecto (una fila por modelo, con cantidad). --}}
@php
    $vacia = ['categoria' => '', 'marca' => '', 'modelo' => '', 'serie' => '', 'cantidad' => 1, 'ubicacion' => '', 'area' => ''];
    $iniciales = old('equipos', $iniciales ?? []);
@endphp
<div x-data="filas(@js(array_values($iniciales)), @js($vacia))" class="space-y-3">
    <datalist id="categorias-equipo">
        @foreach ($categorias as $c)<option value="{{ $c }}">@endforeach
    </datalist>

    <template x-for="(f, i) in filas" :key="i">
        <div class="grid gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-12">
            <input :name="`equipos[${i}][categoria]`" x-model="f.categoria" list="categorias-equipo" placeholder="Equipo (ej. Cámara PTZ)" class="input sm:col-span-3">
            <input :name="`equipos[${i}][marca]`" x-model="f.marca" placeholder="Marca" class="input sm:col-span-2">
            <input :name="`equipos[${i}][modelo]`" x-model="f.modelo" placeholder="Modelo" class="input sm:col-span-2">
            <input :name="`equipos[${i}][serie]`" x-model="f.serie" placeholder="N° serie (si es uno)" class="input sm:col-span-3" @input="if (f.serie) f.cantidad = 1">
            <div class="flex items-center gap-1 sm:col-span-2">
                <input :name="`equipos[${i}][cantidad]`" x-model.number="f.cantidad" type="number" min="1" max="500" class="input w-full" :disabled="!!f.serie" title="Cantidad">
                <span class="text-xs text-slate-500">und.</span>
            </div>
            <input :name="`equipos[${i}][ubicacion]`" x-model="f.ubicacion" placeholder="Ubicación (ej. Poste 14, Av. Balta)" class="input sm:col-span-6">
            <input :name="`equipos[${i}][area]`" x-model="f.area" placeholder="Área" class="input sm:col-span-5">
            <button type="button" class="btn btn-ghost justify-center px-2 text-slate-400 hover:text-red-600 sm:col-span-1" @click="quitar(i)" title="Quitar"><x-icon name="trash" class="size-4" /></button>
        </div>
    </template>

    <div class="flex flex-wrap items-center justify-between gap-2">
        <button type="button" class="btn btn-secondary btn-sm" @click="agregar()"><x-icon name="plus" class="size-4" /> Agregar equipo</button>
        <p class="text-xs text-slate-500" x-show="filas.length" x-text="'Se registrarán ' + filas.filter(f => f.categoria).reduce((t, f) => t + (f.serie ? 1 : Number(f.cantidad || 1)), 0) + ' equipo(s)'"></p>
    </div>
    <p class="help">Una fila por modelo: si instaló 120 cámaras iguales, ponga cantidad 120 y cada una recibirá su código EQ- y su sticker QR. Las series se pueden completar después en cada equipo, o importar todo desde Excel.</p>
    @foreach ($errors->get('equipos.*') as $mensajes)
        @foreach ($mensajes as $m)<p class="error">{{ $m }}</p>@endforeach
    @endforeach
</div>
