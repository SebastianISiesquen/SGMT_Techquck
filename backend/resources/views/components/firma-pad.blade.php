@props(['name', 'label', 'requerido' => false])
<div x-data="firmaPad" {{ $attributes->only('class') }}>
    <div class="mb-1 flex items-center justify-between">
        <span class="label mb-0">{{ $label }} @if ($requerido)<span class="text-red-500">*</span>@endif</span>
        <button type="button" class="text-xs font-medium text-slate-500 hover:text-red-600" @click="limpiar()">Borrar</button>
    </div>
    <div class="relative">
        <canvas x-ref="canvas" class="block h-[180px] w-full cursor-crosshair rounded-lg border-2 border-dashed border-slate-300 bg-white" style="touch-action: none;"></canvas>
        <span x-show="vacio" class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm text-slate-400">Firme aquí con el mouse o el dedo</span>
        <span class="pointer-events-none absolute right-4 bottom-8 left-4 border-b border-slate-300"></span>
    </div>
    <input type="hidden" name="{{ $name }}" x-ref="valor">
    @error($name)<p class="error">{{ $message }}</p>@enderror
</div>
