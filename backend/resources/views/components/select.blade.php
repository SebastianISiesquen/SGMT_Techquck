@props(['name', 'label' => null, 'options' => [], 'value' => null, 'required' => false, 'placeholder' => 'Seleccione…', 'help' => null, 'list' => false])
@php
    $id = 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $actual = old($name, $value instanceof \BackedEnum ? $value->value : $value);
    // Con :list="true" las opciones son una lista simple: el texto es también el valor.
    $opciones = $list ? collect($options)->mapWithKeys(fn ($o) => [$o => $o]) : collect($options);
    if ($list && $actual && ! $opciones->has($actual)) {
        $opciones->put($actual, $actual);
    }
@endphp
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="label">{{ $label }} @if ($required)<span class="text-red-500">*</span>@endif</label>
    @endif
    <select id="{{ $id }}" name="{{ $name }}" @if ($required) required @endif
        {{ $attributes->except('class')->merge(['class' => 'input'.($errors->has($name) ? ' input-error' : '')]) }}>
        @if ($placeholder !== false)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($opciones as $k => $v)
            <option value="{{ $k }}" @selected((string) $actual === (string) $k)>{{ $v }}</option>
        @endforeach
    </select>
    @if ($help)<p class="help">{{ $help }}</p>@endif
    @error($name)<p class="error">{{ $message }}</p>@enderror
</div>
