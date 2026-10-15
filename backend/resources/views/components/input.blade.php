@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'help' => null])
@php
    $id = 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $valor = old($name, $value instanceof \DateTimeInterface
        ? $value->format($type === 'datetime-local' ? 'Y-m-d\TH:i' : 'Y-m-d')
        : $value);
@endphp
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="label">{{ $label }} @if ($required)<span class="text-red-500">*</span>@endif</label>
    @endif
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : $valor }}"
        @if ($required) required @endif
        {{ $attributes->except('class')->merge(['class' => 'input'.($errors->has($name) ? ' input-error' : '')]) }}>
    @if ($help)<p class="help">{{ $help }}</p>@endif
    @error($name)<p class="error">{{ $message }}</p>@enderror
</div>
