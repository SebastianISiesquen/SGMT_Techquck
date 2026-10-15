<?php

namespace App\Notifications;

use App\Models\Mantenimiento;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso de una visita de mantenimiento programada que se acerca. */
class MantenimientoProximo extends Notification
{
    use Canales;

    public function __construct(public Mantenimiento $mantenimiento) {}

    public function toArray(object $notifiable): array
    {
        $m = $this->mantenimiento;
        $dias = $m->diasRestantes();
        $cuando = $dias > 0 ? "en $dias día(s)" : ($dias === 0 ? 'hoy' : 'atrasado');

        return [
            'tipo' => 'mantenimiento',
            'titulo' => "{$m->etiqueta()} · $cuando",
            'mensaje' => "{$m->plan->nombre} · {$m->plan->proyecto->clientes()} · {$m->fecha_programada->format('d/m/Y')}"
                .($m->tecnico ? " · {$m->tecnico->name}" : ' · Sin técnico asignado'),
            'url' => route('mantenimientos.index', ['plan_id' => $m->plan_id]),
            'mantenimiento_id' => $m->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->toArray($notifiable);

        return (new MailMessage)->subject($d['titulo'])->line($d['mensaje'])->action('Ver mantenimientos', $d['url']);
    }
}
