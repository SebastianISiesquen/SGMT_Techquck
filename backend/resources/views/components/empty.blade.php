@props(['icon' => 'folder', 'text' => 'No hay registros.'])
<div class="flex flex-col items-center justify-center px-4 py-10 text-center">
    <div class="mb-3 flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
        <x-icon :name="$icon" class="size-6" />
    </div>
    <p class="text-sm text-slate-500">{{ $text }}</p>
    {{ $slot }}
</div>
