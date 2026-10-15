@php($empresa = \App\Support\Ajustes::get('empresa_nombre'))
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Stickers de equipos</title>
    <style>
        @page { margin: 12mm 10mm; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #0b1d3a; }
        table { border-collapse: separate; border-spacing: 4mm 4mm; width: 100%; }
        td { width: 33%; border: 1px dashed #94a3b8; border-radius: 3mm; padding: 2.5mm; vertical-align: middle; }
        .qr { width: 24mm; height: 24mm; float: left; margin-right: 2mm; }
        .codigo { font-size: 13px; font-weight: bold; font-family: 'DejaVu Sans Mono', monospace; }
        .equipo { font-size: 7.5px; margin-top: 1mm; }
        .empresa { font-size: 6.5px; color: #475569; margin-top: 1mm; }
    </style>
</head>
<body>
<table>
    @foreach ($equipos->chunk(3) as $fila)
        <tr>
            @foreach ($fila as $e)
                <td>
                    <img class="qr" src="{{ \App\Support\Qr::dataUri($e->contenidoQr(), 200) }}">
                    <div class="codigo">{{ $e->codigo }}</div>
                    <div class="equipo">{{ \Illuminate\Support\Str::limit(trim($e->categoria.' '.$e->marca.' '.$e->modelo), 38) }}</div>
                    @if ($e->serie)<div class="equipo">S/N {{ \Illuminate\Support\Str::limit($e->serie, 24) }}</div>@endif
                    <div class="empresa">{{ \Illuminate\Support\Str::limit($e->entidad->nombre, 34) }}<br>Soporte: {{ $empresa }}</div>
                </td>
            @endforeach
            @for ($i = $fila->count(); $i < 3; $i++)<td style="border: none;"></td>@endfor
        </tr>
    @endforeach
</table>
</body>
</html>
