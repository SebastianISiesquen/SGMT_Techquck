<?php

use App\Support\Ajustes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Envíos automáticos. Requiere ejecutar «php artisan schedule:run» cada minuto:
//   producción (Linux): cron  * * * * * php /ruta/artisan schedule:run
//   esta PC (Windows):  tarea «SGGP Programador» (scripts/programador.vbs)
// Las horas se cambian en Configuración → Alertas.
$hora = function (string $clave, string $porDefecto): string {
    try {
        $valor = (string) Ajustes::get($clave);
    } catch (\Throwable) {
        return $porDefecto; // sin base de datos (instalación inicial)
    }

    return preg_match('/^\d{2}:\d{2}$/', $valor) ? $valor : $porDefecto;
};

/*
 * ¿Ya toca? Desde la hora configurada en adelante, si todavía no se ejecutó después de esa hora.
 * Así no se pierde el envío si justo en ese minuto la PC estaba apagada o la tarea no corrió,
 * y si se cambia la hora a una más tarde el mismo día, vuelve a ejecutarse a la nueva hora.
 */
$toca = function (string $clave, string $porDefecto, bool $soloDiaUno = false) use ($hora): bool {
    $programada = Carbon::parse(today()->toDateString().' '.$hora($clave, $porDefecto));
    if ($soloDiaUno) {
        // Informe mensual: el día 1, o el primer día que se pueda si ese día no corrió
        $programada = $programada->startOfMonth()->setTimeFrom($programada);
    }
    $ultima = Cache::get("sggp.ultima_ejecucion.$clave");

    return now()->gte($programada) && (! $ultima || Carbon::parse($ultima)->lt($programada));
};

$marcar = fn (string $clave) => fn () => Cache::forever("sggp.ultima_ejecucion.$clave", now()->toDateTimeString());

// Alertas de garantías por vencer, mantenimientos próximos e incidencias sin atender (RF14)
Schedule::command('sggp:alertas')->everyMinute()->withoutOverlapping()
    ->when(fn () => $toca('hora_alertas', '07:00'))
    ->before($marcar('hora_alertas'));

// Informe del mes anterior a los clientes que lo tienen activado (día 1 de cada mes)
Schedule::command('sggp:informes')->everyMinute()->withoutOverlapping()
    ->when(fn () => $toca('hora_informes', '07:30', soloDiaUno: true))
    ->before($marcar('hora_informes'));
