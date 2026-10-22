{{--
    Encabezado común de los PDF.
    Parámetros: $titulo (ej. «HOJA DE SERVICIO»), $numero (opcional), $datos (pares etiqueta => valor del recuadro), $pie (texto del pie).
--}}
@php
    $emp = \App\Support\Ajustes::all();
    $logo = \App\Services\Pdf\Imagenes::logo();
    // Sin logo: iniciales del nombre, sin la forma societaria (S.A.C., E.I.R.L., S.R.L.)
    $palabras = collect(preg_split('/\s+/', trim($emp['empresa_nombre'])))
        ->reject(fn ($p) => preg_match('/^(S\.?A\.?C?\.?|S\.?A\.?A\.?|E\.?I\.?R\.?L\.?|S\.?R\.?L\.?)$/i', $p) || ! preg_match('/\pL/u', $p))
        ->values();
    $iniciales = $palabras->count() > 1
        ? $palabras->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('')
        : mb_strtoupper(mb_substr($palabras->first() ?? 'S', 0, 2));
@endphp
<div class="cabecera">
    <table>
        <tr>
            <td class="logo">
                @if ($logo)<img src="{{ $logo }}">@else<div class="iniciales">{{ $iniciales }}</div>@endif
            </td>
            <td>
                <div class="empresa">{{ $emp['empresa_nombre'] }}</div>
                <div class="datos-empresa">
                    RUC {{ $emp['empresa_ruc'] }}<br>
                    {{ $emp['empresa_direccion'] }}<br>
                    {{ collect([$emp['empresa_telefono'] ? 'Tel. '.$emp['empresa_telefono'] : null, $emp['empresa_email'] ?: null])->filter()->implode(' · ') }}
                </div>
            </td>
            <td class="caja-titulo">
                <div class="tit">{{ $titulo }}</div>
                @if (! empty($numero))<div class="num">{{ $numero }}</div>@endif
                @if (! empty($datos))
                    <table>
                        @foreach ($datos as $k => $v)
                            <tr><td class="k">{{ $k }}</td><td>{{ $v }}</td></tr>
                        @endforeach
                    </table>
                @endif
            </td>
        </tr>
    </table>
    <div class="linea"></div>
</div>
<div class="pie">
    <span>{{ $pie ?? $emp['empresa_nombre'] }} · Generado el {{ now()->format('d/m/Y h:i A') }}</span>
    <span class="pag" style="float: right;"></span>
</div>
