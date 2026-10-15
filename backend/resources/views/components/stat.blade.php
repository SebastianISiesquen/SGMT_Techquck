@props(['label', 'value', 'icon' => 'chart', 'tone' => 'brand', 'href' => null, 'hint' => null])
@php
    $tonos = [
        'brand' => 'bg-brand-50 text-brand-600',
        'green' => 'bg-emerald-50 text-emerald-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'red' => 'bg-red-50 text-red-600',
        'violet' => 'bg-violet-50 text-violet-600',
        'slate' => 'bg-slate-100 text-slate-600',
    ];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif class="card flex items-center gap-4 p-4 {{ $href ? 'transition hover:border-brand-200 hover:shadow' : '' }}">
    <div class="flex size-11 shrink-0 items-center justify-center rounded-lg {{ $tonos[$tone] ?? $tonos['brand'] }}">
        <x-icon :name="$icon" class="size-5" />
    </div>
    <div class="min-w-0">
        <p class="truncate text-xs font-medium text-slate-500">{{ $label }}</p>
        <p class="text-2xl font-semibold text-slate-900 tabular-nums">{{ $value }}</p>
        @if ($hint)<p class="truncate text-xs text-slate-500">{{ $hint }}</p>@endif
    </div>
</{{ $tag }}>
