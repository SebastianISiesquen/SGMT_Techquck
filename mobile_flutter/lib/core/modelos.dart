/// Modelos de las respuestas JSON de la API (routes/api.php del backend Laravel).
library;

typedef Json = Map<String, dynamic>;

String? _s(dynamic v) => v?.toString();
int _i(dynamic v) => v is int ? v : int.tryParse('$v') ?? 0;
double _d(dynamic v) => v is num ? v.toDouble() : double.tryParse('$v') ?? 0;
bool _b(dynamic v) => v == true || v == 1 || v == '1';
List<T> _lista<T>(dynamic v, T Function(Json) f) => v is List ? v.whereType<Map>().map((e) => f(Json.from(e))).toList() : <T>[];
T? _obj<T>(dynamic v, T Function(Json) f) => v is Map ? f(Json.from(v)) : null;

class Usuario {
  final int id;
  final String nombre, email, rol, iniciales;
  final String? cargo, telefono;
  final int noLeidas;

  Usuario.fromJson(Json j)
    : id = _i(j['id']),
      nombre = _s(j['nombre']) ?? '',
      email = _s(j['email']) ?? '',
      cargo = _s(j['cargo']),
      telefono = _s(j['telefono']),
      rol = _s(j['rol']) ?? '',
      iniciales = _s(j['iniciales']) ?? '',
      noLeidas = _i(j['no_leidas']);

  String get primerNombre => nombre.split(' ').first;
}

class Archivo {
  final int id;
  final String nombre, categoria, categoriaLabel, url, fecha;
  final String? descripcion;
  final bool esImagen;

  Archivo.fromJson(Json j)
    : id = _i(j['id']),
      nombre = _s(j['nombre']) ?? '',
      categoria = _s(j['categoria']) ?? '',
      categoriaLabel = _s(j['categoria_label']) ?? '',
      descripcion = _s(j['descripcion']),
      esImagen = _b(j['es_imagen']),
      url = _s(j['url']) ?? '',
      fecha = _s(j['fecha']) ?? '';
}

class Atencion {
  final int id;
  final String? tecnico, observaciones;
  final String fechaInicio, diagnostico, actividades, resultado, resultadoLabel;
  final List<Archivo> archivos;

  Atencion.fromJson(Json j)
    : id = _i(j['id']),
      tecnico = _s(j['tecnico']),
      fechaInicio = _s(j['fecha_inicio']) ?? '',
      diagnostico = _s(j['diagnostico']) ?? '',
      actividades = _s(j['actividades']) ?? '',
      observaciones = _s(j['observaciones']),
      resultado = _s(j['resultado']) ?? '',
      resultadoLabel = _s(j['resultado_label']) ?? '',
      archivos = _lista(j['archivos'], Archivo.fromJson);
}

class ProyectoIncidencia {
  final int id;
  final String codigo, nombre, entidad;
  final String? ubicacion, contratante, contacto, contactoTelefono;

  ProyectoIncidencia.fromJson(Json j)
    : id = _i(j['id']),
      codigo = _s(j['codigo']) ?? '',
      nombre = _s(j['nombre']) ?? '',
      ubicacion = _s(j['ubicacion']),
      entidad = _s(j['entidad']) ?? '',
      contratante = _s(j['contratante']),
      contacto = _s(j['contacto']),
      contactoTelefono = _s(j['contacto_telefono']);
}

class Antecedente {
  final String fecha, tipoLabel, titulo;
  Antecedente.fromJson(Json j) : fecha = _s(j['fecha']) ?? '', tipoLabel = _s(j['tipo_label']) ?? '', titulo = _s(j['titulo']) ?? '';
}

class EquipoIncidencia {
  final int id;
  final String codigo, categoria, nombre;
  final String? ubicacion, responsable;
  final List<Antecedente> antecedentes;

  EquipoIncidencia.fromJson(Json j)
    : id = _i(j['id']),
      codigo = _s(j['codigo']) ?? '',
      categoria = _s(j['categoria']) ?? '',
      nombre = _s(j['nombre']) ?? '',
      ubicacion = _s(j['ubicacion']),
      responsable = _s(j['responsable']),
      antecedentes = _lista(j['antecedentes'], Antecedente.fromJson);
}

class HojaResumen {
  final int id;
  final String numero, estado, estadoLabel;
  HojaResumen.fromJson(Json j)
    : id = _i(j['id']),
      numero = _s(j['numero']) ?? '',
      estado = _s(j['estado']) ?? '',
      estadoLabel = _s(j['estado_label']) ?? '';
}

class GarantiaIncidencia {
  final String codigo, tipo, fechaVencimiento, estado, estadoLabel;
  final String? cobertura, condiciones;
  GarantiaIncidencia.fromJson(Json j)
    : codigo = _s(j['codigo']) ?? '',
      tipo = _s(j['tipo']) ?? '',
      cobertura = _s(j['cobertura']),
      condiciones = _s(j['condiciones']),
      fechaVencimiento = _s(j['fecha_vencimiento']) ?? '',
      estado = _s(j['estado']) ?? '',
      estadoLabel = _s(j['estado_label']) ?? '';
}

class EventoHistorial {
  final String accion, fecha;
  final String? detalle, estadoNuevo, usuario;
  EventoHistorial.fromJson(Json j)
    : accion = _s(j['accion']) ?? '',
      detalle = _s(j['detalle']),
      estadoNuevo = _s(j['estado_nuevo']),
      usuario = _s(j['usuario']),
      fecha = _s(j['fecha']) ?? '';
}

class Incidencia {
  final int id;
  final String codigo, titulo, descripcion, estado, estadoLabel, prioridad, prioridadLabel;
  final String fechaReporte, cobertura, coberturaLabel;
  final String? ubicacionDetalle, reportadoPor, fechaLimite, proximaVisita, motivoReprogramacion;
  final bool slaVencido, puedeAtender;
  final ProyectoIncidencia proyecto;
  final EquipoIncidencia? equipo;
  final GarantiaIncidencia? garantia;
  final List<HojaResumen> hojas;
  final List<Atencion> atenciones;
  final List<Archivo> archivos;
  final List<EventoHistorial> historial;

  Incidencia.fromJson(Json j)
    : id = _i(j['id']),
      codigo = _s(j['codigo']) ?? '',
      titulo = _s(j['titulo']) ?? '',
      descripcion = _s(j['descripcion']) ?? '',
      estado = _s(j['estado']) ?? '',
      estadoLabel = _s(j['estado_label']) ?? '',
      prioridad = _s(j['prioridad']) ?? '',
      prioridadLabel = _s(j['prioridad_label']) ?? '',
      reportadoPor = _s(j['reportado_por']),
      ubicacionDetalle = _s(j['ubicacion_detalle']),
      fechaReporte = _s(j['fecha_reporte']) ?? '',
      fechaLimite = _s(j['fecha_limite']),
      slaVencido = _b(j['sla_vencido']),
      cobertura = _s(j['cobertura']) ?? '',
      coberturaLabel = _s(j['cobertura_label']) ?? '',
      proximaVisita = _s(j['proxima_visita']),
      motivoReprogramacion = _s(j['motivo_reprogramacion']),
      puedeAtender = _b(j['puede_atender']),
      proyecto = ProyectoIncidencia.fromJson(Json.from(j['proyecto'] as Map)),
      equipo = _obj(j['equipo'], EquipoIncidencia.fromJson),
      garantia = _obj(j['garantia'], GarantiaIncidencia.fromJson),
      hojas = _lista(j['hojas'], HojaResumen.fromJson),
      atenciones = _lista(j['atenciones'], Atencion.fromJson),
      archivos = _lista(j['archivos'], Archivo.fromJson),
      historial = _lista(j['historial'], EventoHistorial.fromJson);
}

class Resumen {
  final int asignadas, enProceso, resueltasMes, criticas;
  Resumen.fromJson(Json j)
    : asignadas = _i(j['asignadas']),
      enProceso = _i(j['en_proceso']),
      resueltasMes = _i(j['resueltas_mes']),
      criticas = _i(j['criticas']);
}

class Notificacion {
  final String id, titulo, mensaje, fecha;
  final int? incidenciaId;
  bool leida;
  Notificacion.fromJson(Json j)
    : id = _s(j['id']) ?? '',
      titulo = _s(j['titulo']) ?? '',
      mensaje = _s(j['mensaje']) ?? '',
      incidenciaId = j['incidencia_id'] == null ? null : _i(j['incidencia_id']),
      leida = _b(j['leida']),
      fecha = _s(j['fecha']) ?? '';
}

class EquipoHoja {
  final String equipo, accion, estadoFinal;
  final String? marca, modelo, serie, observacion;
  EquipoHoja.fromJson(Json j)
    : equipo = _s(j['equipo']) ?? '',
      marca = _s(j['marca']),
      modelo = _s(j['modelo']),
      serie = _s(j['serie']),
      accion = _s(j['accion']) ?? '',
      estadoFinal = _s(j['estado_final']) ?? '',
      observacion = _s(j['observacion']);
}

class Medicion {
  final String parametro, valor;
  final String? unidad;
  Medicion.fromJson(Json j) : parametro = _s(j['parametro']) ?? '', valor = _s(j['valor']) ?? '', unidad = _s(j['unidad']);
}

class HojaServicio {
  final int id;
  final String numero, tipoServicioLabel, estado, estadoLabel, fecha, horaLlegada, trabajoRealizado, pdfUrl;
  final String? horaSalida, diagnostico, resultado, resultadoLabel, observaciones, recomendaciones;
  final String? receptorNombre, receptorCargo, pendientes, proximaVisita, mantenimiento;
  final bool tieneActa, puedeSubirActa;
  final int proyectoId;
  final String proyectoNombre, proyectoEntidad;
  final int? incidenciaId;
  final String? incidenciaCodigo;
  final List<EquipoHoja> equipos;
  final List<Medicion> mediciones;
  final List<Archivo> archivos;

  HojaServicio.fromJson(Json j)
    : id = _i(j['id']),
      numero = _s(j['numero']) ?? '',
      tipoServicioLabel = _s(j['tipo_servicio_label']) ?? '',
      estado = _s(j['estado']) ?? '',
      estadoLabel = _s(j['estado_label']) ?? '',
      fecha = _s(j['fecha']) ?? '',
      horaLlegada = _s(j['hora_llegada']) ?? '',
      horaSalida = _s(j['hora_salida']),
      diagnostico = _s(j['diagnostico']),
      resultado = _s(j['resultado']),
      resultadoLabel = _s(j['resultado_label']),
      trabajoRealizado = _s(j['trabajo_realizado']) ?? '',
      observaciones = _s(j['observaciones']),
      recomendaciones = _s(j['recomendaciones']),
      receptorNombre = _s(j['receptor_nombre']),
      receptorCargo = _s(j['receptor_cargo']),
      tieneActa = _b(j['tiene_acta']),
      puedeSubirActa = _b(j['puede_subir_acta']),
      pendientes = _s(j['pendientes']),
      proximaVisita = _s(j['proxima_visita']),
      mantenimiento = _s(j['mantenimiento']),
      proyectoId = _i((j['proyecto'] as Map?)?['id']),
      proyectoNombre = _s((j['proyecto'] as Map?)?['nombre']) ?? '',
      proyectoEntidad = _s((j['proyecto'] as Map?)?['entidad']) ?? '',
      incidenciaId = (j['incidencia'] as Map?)?['id'] == null ? null : _i((j['incidencia'] as Map)['id']),
      incidenciaCodigo = _s((j['incidencia'] as Map?)?['codigo']),
      equipos = _lista(j['equipos'], EquipoHoja.fromJson),
      mediciones = _lista(j['mediciones'], Medicion.fromJson),
      archivos = _lista(j['archivos'], Archivo.fromJson),
      pdfUrl = _s(j['pdf_url']) ?? '';
}

class GarantiaOpcion {
  final int id;
  final String texto;
  GarantiaOpcion.fromJson(Json j) : id = _i(j['id']), texto = _s(j['texto']) ?? '';
}

class ProyectoOpcion {
  final int id;
  final String codigo, nombre, entidad;
  final List<GarantiaOpcion> garantias;
  ProyectoOpcion.fromJson(Json j)
    : id = _i(j['id']),
      codigo = _s(j['codigo']) ?? '',
      nombre = _s(j['nombre']) ?? '',
      entidad = _s(j['entidad']) ?? '',
      garantias = _lista(j['garantias'], GarantiaOpcion.fromJson);
}

class EquipoEvento {
  final int id;
  final String tipo, tipoLabel, color, fecha, titulo;
  final String? detalle, componente, usuario, incidencia, hoja;
  EquipoEvento.fromJson(Json j)
    : id = _i(j['id']),
      tipo = _s(j['tipo']) ?? '',
      tipoLabel = _s(j['tipo_label']) ?? '',
      color = _s(j['color']) ?? 'slate',
      fecha = _s(j['fecha']) ?? '',
      titulo = _s(j['titulo']) ?? '',
      detalle = _s(j['detalle']),
      componente = _s(j['componente']),
      usuario = _s(j['usuario']),
      incidencia = _s(j['incidencia']),
      hoja = _s(j['hoja']);
}

class GarantiaEquipo {
  final String codigo, tipo, fechaVencimiento;
  final int diasRestantes;
  GarantiaEquipo.fromJson(Json j)
    : codigo = _s(j['codigo']) ?? '',
      tipo = _s(j['tipo']) ?? '',
      fechaVencimiento = _s(j['fecha_vencimiento']) ?? '',
      diasRestantes = _i(j['dias_restantes']);
}

class Equipo {
  final int id;
  final String codigo, categoria, nombre, estado, estadoLabel, cliente;
  final String? marca, modelo, serie, ubicacion, area, responsable;
  final int? proyectoId;
  final String? proyectoCodigo, proyectoNombre;
  final GarantiaEquipo? garantia;
  final List<EquipoEvento> eventos;

  Equipo.fromJson(Json j)
    : id = _i(j['id']),
      codigo = _s(j['codigo']) ?? '',
      categoria = _s(j['categoria']) ?? '',
      marca = _s(j['marca']),
      modelo = _s(j['modelo']),
      serie = _s(j['serie']),
      nombre = _s(j['nombre']) ?? '',
      ubicacion = _s(j['ubicacion']),
      area = _s(j['area']),
      responsable = _s(j['responsable']),
      estado = _s(j['estado']) ?? '',
      estadoLabel = _s(j['estado_label']) ?? '',
      cliente = _s(j['cliente']) ?? '',
      proyectoId = (j['proyecto'] as Map?)?['id'] == null ? null : _i((j['proyecto'] as Map)['id']),
      proyectoCodigo = _s((j['proyecto'] as Map?)?['codigo']),
      proyectoNombre = _s((j['proyecto'] as Map?)?['nombre']),
      garantia = _obj(j['garantia'], GarantiaEquipo.fromJson),
      eventos = _lista(j['eventos'], EquipoEvento.fromJson);
}

class Mantenimiento {
  final int id, diasRestantes, proyectoId;
  final String etiqueta, plan, fechaProgramada, cliente;
  final String? alcance;
  final bool atrasado;
  Mantenimiento.fromJson(Json j)
    : id = _i(j['id']),
      etiqueta = _s(j['etiqueta']) ?? '',
      plan = _s(j['plan']) ?? '',
      alcance = _s(j['alcance']),
      fechaProgramada = _s(j['fecha_programada']) ?? '',
      diasRestantes = _i(j['dias_restantes']),
      atrasado = _b(j['atrasado']),
      proyectoId = _i((j['proyecto'] as Map?)?['id']),
      cliente = _s(j['cliente']) ?? '';
}

class ArticuloAlmacen {
  final int id;
  final String codigo, nombre, unidad;
  final double stock;
  ArticuloAlmacen.fromJson(Json j)
    : id = _i(j['id']),
      codigo = _s(j['codigo']) ?? '',
      nombre = _s(j['nombre']) ?? '',
      unidad = _s(j['unidad']) ?? 'und',
      stock = _d(j['stock']);

  String get stockTexto => stock == stock.roundToDouble() ? stock.toStringAsFixed(0) : stock.toString();
}
