{{-- Registro fotográfico en dos columnas. $fotos viene de App\Services\Pdf\Imagenes::paraPdf(). --}}
@php($alto = \App\Services\Pdf\Imagenes::ALTO)
<table class="fotos">
    @foreach ($fotos->chunk(2) as $fila)
        <tr class="evitar-corte">
            @foreach ($fila->values() as $k => $f)
                <td class="{{ $k === 0 ? 'izq' : 'der' }}">
                    <div class="foto-leyenda">{{ \Illuminate\Support\Str::limit($f['leyenda'], 60) }}</div>
                    <div class="foto-caja" style="height: {{ $alto + 8 }}px;">
                        <img src="{{ $f['src'] }}" style="width: {{ $f['ancho'] }}px; height: {{ $f['alto'] }}px; margin-top: {{ $f['margen'] + 4 }}px;">
                    </div>
                </td>
            @endforeach
            @if ($fila->count() < 2)<td class="der"></td>@endif
        </tr>
    @endforeach
</table>
