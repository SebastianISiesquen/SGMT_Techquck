<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\EquipoApiController;
use App\Http\Controllers\Api\HojaServicioApiController;
use App\Http\Controllers\Api\IncidenciaApiController;
use Illuminate\Support\Facades\Route;

/*
| API para la app móvil del Técnico. Autenticación por token (Laravel Sanctum):
| el token se envía en la cabecera  Authorization: Bearer <token>
*/

Route::post('login', [AuthApiController::class, 'login'])->middleware('throttle:6,1');

Route::middleware(['auth:sanctum', 'activo'])->group(function () {
    Route::get('me', [AuthApiController::class, 'me']);
    Route::post('logout', [AuthApiController::class, 'logout']);
    Route::get('notificaciones', [AuthApiController::class, 'notificaciones']);
    Route::post('notificaciones/leer', [AuthApiController::class, 'leerNotificaciones']);

    Route::get('resumen', [IncidenciaApiController::class, 'resumen']);
    Route::get('incidencias', [IncidenciaApiController::class, 'index']);
    Route::get('incidencias/{incidencia}', [IncidenciaApiController::class, 'show']);
    Route::post('incidencias/{incidencia}/iniciar', [IncidenciaApiController::class, 'iniciar']);
    Route::post('incidencias/{incidencia}/atenciones', [IncidenciaApiController::class, 'atender']);
    Route::post('incidencias/{incidencia}/archivos', [IncidenciaApiController::class, 'subirArchivo']);
    Route::get('archivos/{archivo}', [IncidenciaApiController::class, 'archivo']);

    Route::get('proyectos', [HojaServicioApiController::class, 'proyectos']);
    Route::get('hojas', [HojaServicioApiController::class, 'index']);
    Route::post('hojas', [HojaServicioApiController::class, 'store']);
    Route::get('hojas/{hoja}', [HojaServicioApiController::class, 'show']);
    Route::post('hojas/{hoja}/firmar', [HojaServicioApiController::class, 'firmar']);

    Route::get('equipos', [EquipoApiController::class, 'index']);
    Route::get('equipos/codigo/{codigo}', [EquipoApiController::class, 'porCodigo'])->where('codigo', '.*');
    Route::get('equipos/{equipo}', [EquipoApiController::class, 'show']);
    Route::get('mantenimientos', [EquipoApiController::class, 'mantenimientos']);
    Route::get('almacen', [HojaServicioApiController::class, 'almacen']);
    Route::get('checklists', [HojaServicioApiController::class, 'checklists']);
});
