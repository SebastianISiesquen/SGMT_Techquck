<?php

use Illuminate\Support\Facades\Schedule;

// Alertas de garantías por vencer e incidencias sin atender (RF14).
// En producción requiere el cron: * * * * * php /ruta/artisan schedule:run
// Día 1 de cada mes: informe del mes anterior a los clientes que lo tienen activado
Schedule::command('sggp:informes')->monthlyOn(1, '07:30');

Schedule::command('sggp:alertas')->dailyAt('07:00');
