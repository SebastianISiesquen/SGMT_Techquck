<?php

use App\Http\Controllers\Admin\AuditoriaController;
use App\Http\Controllers\Admin\ConfiguracionController;
use App\Http\Controllers\Admin\RolController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\ArchivoController;
use App\Http\Controllers\AlmacenController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntidadController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\EquipoPublicoController;
use App\Http\Controllers\GarantiaController;
use App\Http\Controllers\HojaServicioController;
use App\Http\Controllers\IncidenciaController;
use App\Http\Controllers\InformeClienteController;
use App\Http\Controllers\MantenimientoController;
use App\Http\Controllers\MisIncidenciasController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\PlanMantenimientoController;
use App\Http\Controllers\ProyectoController;
use App\Http\Controllers\ReporteController;
use Illuminate\Support\Facades\Route;

// Ficha pública del equipo: la abre el QR del sticker desde cualquier celular, sin iniciar sesión
Route::get('e/{token}', [EquipoPublicoController::class, 'show'])->name('equipos.publico')
    ->where('token', '[a-z0-9]{10,32}')->middleware('throttle:60,1');

// PDF de la hoja de servicio con enlace firmado temporal (para abrirlo desde la app móvil)
Route::get('hojas-servicio/{hoja}/pdf-publico', [HojaServicioController::class, 'pdfPublico'])
    ->name('hojas.pdf-publico')->middleware('signed');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'create'])->name('login');
    Route::post('login', [AuthController::class, 'store'])->middleware('throttle:6,1');
});

Route::middleware(['auth', 'activo'])->group(function () {
    Route::post('logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', [DashboardController::class, 'inicio'])->name('inicio');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard')->middleware('can:dashboard.ver');

    Route::get('perfil', [PerfilController::class, 'edit'])->name('perfil');
    Route::put('perfil', [PerfilController::class, 'update'])->name('perfil.update');
    Route::put('perfil/password', [PerfilController::class, 'password'])->name('perfil.password');

    Route::get('notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::get('notificaciones/{id}', [NotificacionController::class, 'abrir'])->name('notificaciones.abrir');
    Route::post('notificaciones/leer-todas', [NotificacionController::class, 'leerTodas'])->name('notificaciones.leer-todas');

    // Entidades del Estado (RF03)
    Route::middleware('can:entidades.gestionar')->group(function () {
        Route::get('entidades/create', [EntidadController::class, 'create'])->name('entidades.create');
        Route::post('entidades', [EntidadController::class, 'store'])->name('entidades.store');
        Route::get('entidades/{entidad}/edit', [EntidadController::class, 'edit'])->name('entidades.edit');
        Route::put('entidades/{entidad}', [EntidadController::class, 'update'])->name('entidades.update');
        Route::delete('entidades/{entidad}', [EntidadController::class, 'destroy'])->name('entidades.destroy');
    });
    Route::middleware('can:entidades.ver')->group(function () {
        Route::get('entidades', [EntidadController::class, 'index'])->name('entidades.index');
        Route::get('entidades/{entidad}', [EntidadController::class, 'show'])->name('entidades.show');
    });

    // Proyectos ejecutados (RF04)
    Route::middleware('can:proyectos.gestionar')->group(function () {
        Route::get('proyectos/create', [ProyectoController::class, 'create'])->name('proyectos.create');
        Route::post('proyectos', [ProyectoController::class, 'store'])->name('proyectos.store');
        Route::get('proyectos/{proyecto}/edit', [ProyectoController::class, 'edit'])->name('proyectos.edit');
        Route::put('proyectos/{proyecto}', [ProyectoController::class, 'update'])->name('proyectos.update');
        Route::delete('proyectos/{proyecto}', [ProyectoController::class, 'destroy'])->name('proyectos.destroy');
        Route::post('proyectos/{proyecto}/equipos', [ProyectoController::class, 'agregarEquipos'])->name('proyectos.equipos.store');
        Route::put('proyectos/{proyecto}/equipos', [ProyectoController::class, 'actualizarEquipos'])->name('proyectos.equipos.update');
    });
    Route::middleware('can:proyectos.ver')->group(function () {
        Route::get('proyectos', [ProyectoController::class, 'index'])->name('proyectos.index');
        Route::get('proyectos/{proyecto}', [ProyectoController::class, 'show'])->name('proyectos.show');
    });
    Route::get('proyectos/{proyecto}/opciones.json', [ProyectoController::class, 'opciones'])->name('proyectos.opciones');

    // Garantías (RF05–RF06)
    Route::middleware('can:garantias.gestionar')->group(function () {
        Route::get('garantias/create', [GarantiaController::class, 'create'])->name('garantias.create');
        Route::post('garantias', [GarantiaController::class, 'store'])->name('garantias.store');
        Route::get('garantias/{garantia}/edit', [GarantiaController::class, 'edit'])->name('garantias.edit');
        Route::put('garantias/{garantia}', [GarantiaController::class, 'update'])->name('garantias.update');
        Route::delete('garantias/{garantia}', [GarantiaController::class, 'destroy'])->name('garantias.destroy');
    });
    Route::middleware('can:garantias.ver')->group(function () {
        Route::get('garantias', [GarantiaController::class, 'index'])->name('garantias.index');
        Route::get('garantias/{garantia}', [GarantiaController::class, 'show'])->name('garantias.show');
        Route::get('garantias/{garantia}/carta', [GarantiaController::class, 'carta'])->name('garantias.carta');
    });
    Route::post('garantias/{garantia}/avisar', [GarantiaController::class, 'avisar'])->name('garantias.avisar')->middleware('can:garantias.gestionar');

    // Equipos de los clientes y su historial (hoja de vida)
    Route::middleware('can:equipos.gestionar')->group(function () {
        Route::get('equipos/create', [EquipoController::class, 'create'])->name('equipos.create');
        Route::post('equipos', [EquipoController::class, 'store'])->name('equipos.store');
        Route::get('equipos/importar', [EquipoController::class, 'importarForm'])->name('equipos.importar');
        Route::post('equipos/importar', [EquipoController::class, 'importar'])->name('equipos.importar.store');
        Route::get('equipos/plantilla', [EquipoController::class, 'plantilla'])->name('equipos.plantilla');
        Route::get('equipos/{equipo}/edit', [EquipoController::class, 'edit'])->name('equipos.edit');
        Route::put('equipos/{equipo}', [EquipoController::class, 'update'])->name('equipos.update');
        Route::delete('equipos/{equipo}', [EquipoController::class, 'destroy'])->name('equipos.destroy');
        Route::post('equipos/{equipo}/eventos', [EquipoController::class, 'registrarEvento'])->name('equipos.eventos.store');
    });
    Route::middleware('can:equipos.ver')->group(function () {
        Route::get('equipos', [EquipoController::class, 'index'])->name('equipos.index');
        Route::get('equipos/etiquetas', [EquipoController::class, 'etiquetas'])->name('equipos.etiquetas');
        Route::get('equipos/qr/{codigo}', [EquipoController::class, 'qr'])->name('equipos.qr');
        Route::get('equipos/{equipo}', [EquipoController::class, 'show'])->name('equipos.show');
        Route::get('equipos/{equipo}/hoja-de-vida', [EquipoController::class, 'hojaDeVida'])->name('equipos.hoja-vida');
    });
    Route::get('equipos-buscar.json', [EquipoController::class, 'buscar'])->name('equipos.buscar');

    // Checklists de mantenimiento por categoría de equipo
    Route::middleware('can:equipos.gestionar')->group(function () {
        Route::get('checklists', [ChecklistController::class, 'edit'])->name('checklists.edit');
        Route::put('checklists', [ChecklistController::class, 'update'])->name('checklists.update');
    });

    // Almacén: consumibles y repuestos
    Route::middleware('can:almacen.gestionar')->group(function () {
        Route::get('almacen/create', [AlmacenController::class, 'create'])->name('almacen.create');
        Route::post('almacen', [AlmacenController::class, 'store'])->name('almacen.store');
        Route::get('almacen/{articulo}/edit', [AlmacenController::class, 'edit'])->name('almacen.edit');
        Route::put('almacen/{articulo}', [AlmacenController::class, 'update'])->name('almacen.update');
        Route::delete('almacen/{articulo}', [AlmacenController::class, 'destroy'])->name('almacen.destroy');
        Route::post('almacen/{articulo}/movimientos', [AlmacenController::class, 'movimiento'])->name('almacen.movimientos.store');
    });
    Route::middleware('can:almacen.ver')->group(function () {
        Route::get('almacen', [AlmacenController::class, 'index'])->name('almacen.index');
        Route::get('almacen/{articulo}', [AlmacenController::class, 'show'])->name('almacen.show');
    });
    Route::get('almacen-articulos.json', [AlmacenController::class, 'opciones'])->name('almacen.opciones');

    // Informe periódico al cliente
    Route::middleware('can:proyectos.ver')->group(function () {
        Route::get('proyectos/{proyecto}/informe', [InformeClienteController::class, 'pdf'])->name('proyectos.informe');
        Route::post('proyectos/{proyecto}/informe/enviar', [InformeClienteController::class, 'enviar'])->name('proyectos.informe.enviar')->middleware('can:proyectos.gestionar');
    });

    // Mantenimientos programados (planes con N visitas)
    Route::middleware('can:mantenimientos.gestionar')->group(function () {
        Route::get('planes-mantenimiento/create', [PlanMantenimientoController::class, 'create'])->name('planes.create');
        Route::post('planes-mantenimiento', [PlanMantenimientoController::class, 'store'])->name('planes.store');
        Route::get('planes-mantenimiento/{plan}/edit', [PlanMantenimientoController::class, 'edit'])->name('planes.edit');
        Route::put('planes-mantenimiento/{plan}', [PlanMantenimientoController::class, 'update'])->name('planes.update');
        Route::delete('planes-mantenimiento/{plan}', [PlanMantenimientoController::class, 'destroy'])->name('planes.destroy');
        Route::put('mantenimientos/{mantenimiento}', [MantenimientoController::class, 'update'])->name('mantenimientos.update');
    });
    Route::middleware('can:mantenimientos.ver')->group(function () {
        Route::get('mantenimientos', [MantenimientoController::class, 'index'])->name('mantenimientos.index');
        Route::get('planes-mantenimiento/{plan}', [PlanMantenimientoController::class, 'show'])->name('planes.show');
    });

    // Cotizaciones (servicios sin cobertura)
    Route::middleware('can:cotizaciones.gestionar')->group(function () {
        Route::get('cotizaciones/create', [CotizacionController::class, 'create'])->name('cotizaciones.create');
        Route::post('cotizaciones', [CotizacionController::class, 'store'])->name('cotizaciones.store');
        Route::get('cotizaciones/{cotizacion}/edit', [CotizacionController::class, 'edit'])->name('cotizaciones.edit');
        Route::put('cotizaciones/{cotizacion}', [CotizacionController::class, 'update'])->name('cotizaciones.update');
        Route::post('cotizaciones/{cotizacion}/estado', [CotizacionController::class, 'estado'])->name('cotizaciones.estado');
        Route::delete('cotizaciones/{cotizacion}', [CotizacionController::class, 'destroy'])->name('cotizaciones.destroy');
    });
    Route::middleware('can:cotizaciones.ver')->group(function () {
        Route::get('cotizaciones', [CotizacionController::class, 'index'])->name('cotizaciones.index');
        Route::get('cotizaciones/{cotizacion}', [CotizacionController::class, 'show'])->name('cotizaciones.show');
        Route::get('cotizaciones/{cotizacion}/pdf', [CotizacionController::class, 'pdf'])->name('cotizaciones.pdf');
    });

    // Incidencias (RF07–RF11)
    Route::middleware('can:incidencias.gestionar')->group(function () {
        Route::get('incidencias/create', [IncidenciaController::class, 'create'])->name('incidencias.create');
        Route::post('incidencias', [IncidenciaController::class, 'store'])->name('incidencias.store');
        Route::get('incidencias/{incidencia}/edit', [IncidenciaController::class, 'edit'])->name('incidencias.edit');
        Route::put('incidencias/{incidencia}', [IncidenciaController::class, 'update'])->name('incidencias.update');
    });
    Route::post('incidencias/{incidencia}/reprogramar', [IncidenciaController::class, 'reprogramar'])->name('incidencias.reprogramar')->middleware('can:incidencias.asignar');
    Route::post('incidencias/{incidencia}/asignar', [IncidenciaController::class, 'asignar'])->name('incidencias.asignar')->middleware('can:incidencias.asignar');
    Route::middleware('can:incidencias.cerrar')->group(function () {
        Route::post('incidencias/{incidencia}/cerrar', [IncidenciaController::class, 'cerrar'])->name('incidencias.cerrar');
        Route::post('incidencias/{incidencia}/reabrir', [IncidenciaController::class, 'reabrir'])->name('incidencias.reabrir');
        Route::post('incidencias/{incidencia}/anular', [IncidenciaController::class, 'anular'])->name('incidencias.anular');
    });
    Route::get('incidencias', [IncidenciaController::class, 'index'])->name('incidencias.index')->middleware('can:incidencias.ver');
    Route::get('incidencias/{incidencia}', [IncidenciaController::class, 'show'])->name('incidencias.show');
    Route::get('incidencias/{incidencia}/informe', [IncidenciaController::class, 'informe'])->name('incidencias.informe');

    // Atención técnica (vista del Técnico en la web; la app móvil usa la API)
    Route::middleware('can:atenciones.registrar')->group(function () {
        Route::get('mis-incidencias', [MisIncidenciasController::class, 'index'])->name('mis-incidencias.index');
        Route::get('mis-incidencias/{incidencia}', [MisIncidenciasController::class, 'show'])->name('mis-incidencias.show');
        Route::post('mis-incidencias/{incidencia}/iniciar', [MisIncidenciasController::class, 'iniciar'])->name('mis-incidencias.iniciar');
        Route::post('mis-incidencias/{incidencia}/atenciones', [MisIncidenciasController::class, 'atender'])->name('mis-incidencias.atender');
    });

    // Hojas de servicio en campo
    Route::middleware('can:hojas.registrar')->group(function () {
        Route::get('hojas-servicio/create', [HojaServicioController::class, 'create'])->name('hojas.create');
        Route::post('hojas-servicio', [HojaServicioController::class, 'store'])->name('hojas.store');
    });
    Route::get('hojas-servicio', [HojaServicioController::class, 'index'])->name('hojas.index');
    Route::get('hojas-servicio/{hoja}', [HojaServicioController::class, 'show'])->name('hojas.show');
    Route::get('hojas-servicio/{hoja}/edit', [HojaServicioController::class, 'edit'])->name('hojas.edit');
    Route::put('hojas-servicio/{hoja}', [HojaServicioController::class, 'update'])->name('hojas.update');
    Route::post('hojas-servicio/{hoja}/anular', [HojaServicioController::class, 'anular'])->name('hojas.anular');
    Route::get('hojas-servicio/{hoja}/pdf', [HojaServicioController::class, 'pdf'])->name('hojas.pdf');

    // Documentos y evidencias (almacenamiento privado)
    Route::post('archivos/{tipo}/{id}', [ArchivoController::class, 'store'])->name('archivos.store')
        ->whereIn('tipo', ['proyecto', 'garantia', 'incidencia', 'atencion', 'hoja_servicio', 'equipo']);
    Route::get('archivos/{archivo}/ver', [ArchivoController::class, 'ver'])->name('archivos.ver');
    Route::get('archivos/{archivo}/descargar', [ArchivoController::class, 'descargar'])->name('archivos.descargar');
    Route::delete('archivos/{archivo}', [ArchivoController::class, 'destroy'])->name('archivos.destroy');

    // Reportes (RF15)
    Route::middleware('can:reportes.ver')->group(function () {
        Route::get('reportes', [ReporteController::class, 'index'])->name('reportes.index');
        Route::get('reportes/{tipo}/{formato}', [ReporteController::class, 'exportar'])->name('reportes.exportar')
            ->whereIn('tipo', ['proyectos', 'garantias', 'incidencias', 'atenciones', 'hojas', 'equipos', 'mantenimientos', 'cotizaciones', 'almacen'])
            ->whereIn('formato', ['pdf', 'excel']);
    });

    // Administración
    Route::prefix('admin')->group(function () {
        Route::middleware('can:usuarios.gestionar')->group(function () {
            Route::resource('usuarios', UsuarioController::class)->except('show', 'destroy')->parameters(['usuarios' => 'usuario']);
            Route::patch('usuarios/{usuario}/estado', [UsuarioController::class, 'toggle'])->name('usuarios.toggle');
            Route::get('roles', [RolController::class, 'index'])->name('roles.index');
            Route::put('roles/{rol}', [RolController::class, 'update'])->name('roles.update');
        });
        Route::get('auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index')->middleware('can:auditoria.ver');
        Route::middleware('can:configuracion.gestionar')->group(function () {
            Route::get('configuracion', [ConfiguracionController::class, 'edit'])->name('configuracion.edit');
            Route::put('configuracion', [ConfiguracionController::class, 'update'])->name('configuracion.update');
            Route::post('configuracion/correo-prueba', [ConfiguracionController::class, 'probarCorreo'])->name('configuracion.correo-prueba');
        });
    });
});
