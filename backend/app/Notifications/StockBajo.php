<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** Resumen diario de artículos del almacén que llegaron a su stock mínimo. */
class StockBajo extends Notification
{
    use Canales;

    /** @param  Collection<int, \App\Models\AlmacenArticulo>  $articulos */
    public function __construct(public Collection $articulos) {}

    public function toArray(object $notifiable): array
    {
        $n = $this->articulos->count();

        return [
            'tipo' => 'almacen',
            'titulo' => "$n artículo(s) del almacén por reponer",
            'mensaje' => $this->articulos->take(5)->map(fn ($a) => "{$a->nombre} (".\App\Models\AlmacenArticulo::cantidad($a->stock)." {$a->unidad})")->implode(', ')
                .($n > 5 ? '…' : ''),
            'url' => route('almacen.index', ['stock' => 'bajo']),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->toArray($notifiable);

        return (new MailMessage)->subject($d['titulo'])->line($d['mensaje'])->action('Ver almacén', $d['url']);
    }
}
