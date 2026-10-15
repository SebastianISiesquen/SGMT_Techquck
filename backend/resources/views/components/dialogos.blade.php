{{--
    Ventana de confirmación y avisos flotantes de todo el sistema.
    - Confirmación: <form data-confirmar="¿Eliminar…?" data-confirmar-tipo="peligro" data-confirmar-boton="Eliminar">
    - Aviso desde JS: window.aviso('Texto', 'ok' | 'error' | 'info')
--}}
@php
    $avisos = collect();
    if (session('ok')) $avisos->push(['tipo' => 'ok', 'texto' => session('ok')]);
    if (session('error')) $avisos->push(['tipo' => 'error', 'texto' => session('error')]);
    if ($errors->any() && ! $errors->has('general') && ! $errors->has('estado')) {
        $avisos->push(['tipo' => 'error', 'texto' => $errors->count() === 1 ? 'Hay 1 dato por corregir.' : "Hay {$errors->count()} datos por corregir."]);
    }
@endphp

<div x-data="dialogos(@js($avisos->values()))" @confirmar.window="abrir($event.detail)" @aviso.window="avisar($event.detail)">
    {{-- Confirmación --}}
    <div x-show="visible" x-cloak class="fixed inset-0 z-[60] flex items-end justify-center bg-slate-900/50 p-4 sm:items-center"
         x-transition.opacity @keydown.escape.window="responder(false)">
        <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl" @click.outside="responder(false)" role="dialog" aria-modal="true"
             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="translate-y-4 opacity-0" x-transition:enter-end="translate-y-0 opacity-100">
            <div class="flex gap-4 p-5">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-full"
                     :class="{ 'bg-red-100 text-red-600': tipo === 'peligro', 'bg-amber-100 text-amber-600': tipo === 'advertencia', 'bg-brand-100 text-brand-600': tipo === 'info' }">
                    <template x-if="tipo === 'peligro'"><x-icon name="trash" class="size-5" /></template>
                    <template x-if="tipo === 'advertencia'"><x-icon name="alert" class="size-5" /></template>
                    <template x-if="tipo === 'info'"><x-icon name="send" class="size-5" /></template>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-semibold text-slate-900" x-text="titulo"></h3>
                    <p class="mt-1 whitespace-pre-line text-sm text-slate-600" x-text="mensaje"></p>
                </div>
            </div>
            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50 px-5 py-3 sm:flex-row sm:justify-end">
                <button type="button" class="btn btn-secondary" @click="responder(false)">Cancelar</button>
                <button type="button" class="btn" x-ref="aceptar" @click="responder(true)"
                        :class="tipo === 'peligro' ? 'btn-danger' : (tipo === 'advertencia' ? 'bg-amber-600 text-white hover:bg-amber-700' : 'btn-primary')" x-text="boton"></button>
            </div>
        </div>
    </div>

    {{-- Avisos flotantes --}}
    <div class="pointer-events-none fixed inset-x-0 top-3 z-[70] flex flex-col items-center gap-2 px-4 sm:items-end sm:pr-6">
        <template x-for="a in lista" :key="a.id">
            <div x-show="a.visible" x-transition.opacity.duration.200ms
                 class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border bg-white px-4 py-3 text-sm shadow-lg"
                 :class="a.tipo === 'error' ? 'border-red-200' : (a.tipo === 'ok' ? 'border-emerald-200' : 'border-slate-200')" role="status">
                <span class="mt-0.5 size-2.5 shrink-0 rounded-full" :class="a.tipo === 'error' ? 'bg-red-500' : (a.tipo === 'ok' ? 'bg-emerald-500' : 'bg-brand-500')"></span>
                <p class="flex-1 text-slate-800" x-text="a.texto"></p>
                <button type="button" class="text-slate-400 hover:text-slate-600" @click="a.visible = false" aria-label="Cerrar"><x-icon name="x" class="size-4" /></button>
            </div>
        </template>
    </div>
</div>
