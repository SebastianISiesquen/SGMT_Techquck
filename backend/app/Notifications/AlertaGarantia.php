<?php

namespace App\Notifications;

use App\Models\Garantia;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Alerta interna de garantía próxima a vencer o recién vencida (RF14). */
class AlertaGarantia extends Notification
{
    use Canales;

    public function __construct(public Garantia $garantia, public int $dias, public bool $clienteAvisado = false) {}

    public function toArray(object $notifiable): array
    {
        $g = $this->garantia;
        $titulo = $this->dias > 0
            ? "Garantía {$g->codigo} vence en {$this->dias} día(s)"
            : ($this->dias === 0 ? "Garantía {$g->codigo} vence hoy" : "Garantía {$g->codigo} venció");

        return [
            'tipo' => 'garantia',
            'titulo' => $titulo,
            'mensaje' => "{$g->tipo} · {$g->proyecto->nombre} · {$g->proyecto->clientes()} · vence el {$g->fecha_vencimiento->format('d/m/Y')}"
                .($this->clienteAvisado ? ' · Se avisó al cliente por correo' : ''),
            'url' => route('garantias.show', $g),
            'garantia_id' => $g->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->toArray($notifiable);

        return (new MailMessage)->subject($d['titulo'])
            ->line($d['mensaje'])
            ->line('Coordine con el cliente el último mantenimiento o la renovación del servicio.')
            ->action('Ver garantía', $d['url']);
    }
}
