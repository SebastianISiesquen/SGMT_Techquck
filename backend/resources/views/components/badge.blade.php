@props(['color' => 'slate', 'dot' => true])
@php
    // Clases completas para que Tailwind las detecte al compilar.
    $estilos = [
        'slate' => 'bg-slate-100 text-slate-700 ring-slate-500/20',
        'blue' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
        'green' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-600/30',
        'red' => 'bg-red-50 text-red-700 ring-red-600/20',
        'violet' => 'bg-violet-50 text-violet-700 ring-violet-600/20',
    ];
    $puntos = [
        'slate' => 'bg-slate-400', 'blue' => 'bg-blue-500', 'green' => 'bg-emerald-500',
        'amber' => 'bg-amber-500', 'red' => 'bg-red-500', 'violet' => 'bg-violet-500',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.($estilos[$color] ?? $estilos['slate'])]) }}>
    @if ($dot)<span class="size-1.5 rounded-full {{ $puntos[$color] ?? $puntos['slate'] }}"></span>@endif
    {{ $slot }}
</span>
