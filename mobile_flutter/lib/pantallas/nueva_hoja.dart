import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';

import '../core/api.dart';
import '../core/archivos.dart';
import '../core/borradores.dart';
import '../core/modelos.dart';
import '../core/tema.dart';
import '../widgets/ui.dart';
import 'escanear.dart';
import 'hoja.dart';

const _tipos = [('correctivo', 'Correctivo'), ('preventivo', 'Preventivo'), ('instalacion', 'Instalación'), ('inspeccion', 'Inspección')];
const _acciones = [
  ('revisado', 'Revisado'),
  ('configurado', 'Configurado'),
  ('reparado', 'Reparado'),
  ('cambio_componente', 'Cambio de componente'),
  ('reemplazado', 'Reemplazado'),
  ('instalado', 'Instalado'),
  ('retirado', 'Retirado'),
];
const _estados = [('operativo', 'Operativo'), ('observado', 'Con observ.'), ('inoperativo', 'Inoperativo')];
const _resultados = [
  ('resuelto', 'Resuelto', Icons.check_circle_outline),
  ('parcial', 'No se terminó: vuelvo otro día', Icons.schedule),
  ('pendiente_repuesto', 'Pendiente de repuesto', Icons.inventory_2_outlined),
  ('no_procede', 'No procede', Icons.block),
];
const _continua = ['parcial', 'pendiente_repuesto'];
const _puntos = [
  ('ok', 'OK', Color(0xFF059669)),
  ('observado', 'Obs.', Color(0xFFD97706)),
  ('falla', 'Falla', Color(0xFFDC2626)),
  ('na', 'N/A', Color(0xFF64748B)),
];

/// Misma normalización que el backend para buscar el checklist de una categoría.
String _clave(String t) {
  const acentos = {'á': 'a', 'é': 'e', 'í': 'i', 'ó': 'o', 'ú': 'u', 'ü': 'u', 'ñ': 'n'};
  return t.trim().toLowerCase().split('').map((c) => acentos[c] ?? c).join();
}

class _Punto {
  final String item;
  String resultado = '';
  String nota = '';
  _Punto(this.item);
}

class _Verificacion {
  final String item;
  String resultado = 'ok';
  _Verificacion(this.item);
}

/// Evaluación preliminar del sitio (los mismos aspectos que la web: HojaServicio::EVALUACION_SITIO).
const _evaluacionSitio = [
  ('Ambiente', 'Ej. Data center del 3er piso; poste en la vía pública'),
  ('Equipo de aire acondicionado', 'Ej. 1 aire tipo confort de 24 000 BTU; no tiene aire acondicionado'),
  ('Material inflamable', 'Ej. Cajas de cartón a los costados; equipo sobre cajas de madera'),
  ('Acceso y ubicación', 'Ej. Se accede por una escalera tipo gato'),
];

/// Puntos de medición según el sistema eléctrico.
const _puntosMedicion = {
  'tension': {
    'monofasico': ['L-N', 'L-T', 'N-T'],
    'trifasico': ['R-S', 'S-T', 'T-R', 'R-N', 'S-N', 'T-N', 'N-T'],
  },
  'corriente': {
    'monofasico': ['L', 'N'],
    'trifasico': ['R', 'S', 'T', 'N'],
  },
};

class _EquipoForm {
  String equipoId = '', codigo = '', equipo = '', marca = '', modelo = '', serie = '';
  String accion = 'revisado', estadoFinal = 'operativo', observacion = '';
  List<_Punto> checklist = [];

  /// Equipo con falla de la incidencia: lleva su propio diagnóstico, resultado y fotos (salen en su informe técnico).
  bool deIncidencia = false;
  String diagnostico = '', resultado = '';
  final List<ArchivoLocal> fotos = [];

  _EquipoForm();

  _EquipoForm.inventario(Equipo e, this.accion)
    : equipoId = '${e.id}',
      codigo = e.codigo,
      equipo = e.categoria,
      marca = e.marca ?? '',
      modelo = e.modelo ?? '',
      serie = e.serie ?? '';
}

class _MedicionForm {
  String parametro, unidad, valor = '';

  /// Medición predefinida (tensión, corriente, temperatura): su nombre no se edita, solo el valor.
  final bool fija;
  _MedicionForm(this.parametro, this.unidad, {this.fija = false});
}

class _MaterialForm {
  String articuloId, descripcion, cantidad, unidad;
  _MaterialForm({this.articuloId = '', this.descripcion = '', this.unidad = 'und'}) : cantidad = '1';
}

/// Hoja de servicio de la visita: verificaciones, equipos, trabajo, mediciones, materiales y fotos.
/// Si no se termina, se indica lo pendiente y la próxima visita. La conformidad es el acta
/// firmada a mano, que se fotografía y se sube (ahora o después).
class NuevaHojaPantalla extends StatefulWidget {
  final int? incidenciaId, mantenimientoId, proyectoId;
  final String? etiqueta;
  const NuevaHojaPantalla({super.key, this.incidenciaId, this.mantenimientoId, this.proyectoId, this.etiqueta});

  @override
  State<NuevaHojaPantalla> createState() => _NuevaHojaPantallaState();
}

class _NuevaHojaPantallaState extends State<NuevaHojaPantalla> with WidgetsBindingObserver {
  bool _listo = false;

  // ── Borrador automático en el teléfono ──
  /// Un borrador por visita: de la incidencia, del mantenimiento o de la hoja suelta del proyecto.
  late final String _claveBorrador = widget.incidenciaId != null
      ? 'inc_${widget.incidenciaId}'
      : widget.mantenimientoId != null
      ? 'mant_${widget.mantenimientoId}'
      : 'hoja_${widget.proyectoId ?? 0}';

  /// Identifica esta hoja ante el servidor: un reenvío no la duplica.
  String _uuid = '${DateTime.now().microsecondsSinceEpoch}-${Random.secure().nextInt(1 << 32).toRadixString(16)}';
  Timer? _autoguardado;
  String _ultimoGuardado = '';
  String _inicial = '';
  DateTime? _recuperadoDe;
  String? _errorEnvio;
  bool _pendienteDeEnvio = false;
  bool _enviada = false;
  Incidencia? _incidencia;
  List<ProyectoOpcion> _proyectos = [];
  String _buscar = '';
  int? _proyectoId;
  int? _garantiaId;
  List<Equipo> _inventario = [];
  bool _verInventario = false;
  List<ArticuloAlmacen> _articulos = [];
  bool _verAlmacen = false;
  Map<String, List<String>> _checklists = {};

  late String _tipo = widget.incidenciaId != null ? 'correctivo' : 'preventivo';
  TimeOfDay _llegada = TimeOfDay.fromDateTime(DateTime.now().subtract(const Duration(hours: 1)));
  TimeOfDay? _salida = TimeOfDay.now();
  String _resultado = 'resuelto';
  DateTime _proximaVisita = DateTime.now().add(const Duration(days: 1));

  final _diagnostico = TextEditingController();
  final _trabajo = TextEditingController();
  final _observaciones = TextEditingController();
  final _recomendaciones = TextEditingController();
  final _pendientes = TextEditingController();
  final _receptor = TextEditingController();
  final _dni = TextEditingController();
  final _cargo = TextEditingController();

  List<_Verificacion> _verificaciones = [];
  final Map<String, String> _evaluacion = {for (final (item, _) in _evaluacionSitio) item: ''};
  final List<_EquipoForm> _equipos = [];
  final List<_MedicionForm> _mediciones = [];
  final List<_MaterialForm> _materiales = [];
  final List<ArchivoLocal> _fotos = [];
  final List<ArchivoLocal> _actas = [];
  bool _enviando = false;

  @override
  void initState() {
    super.initState();
    _proyectoId = widget.proyectoId;
    WidgetsBinding.instance.addObserver(this);
    _inicializar();
  }

  /// Si Android pausa la app (cámara, llamada, otra app), se guarda lo llenado.
  @override
  void didChangeAppLifecycleState(AppLifecycleState estado) {
    if (estado == AppLifecycleState.paused || estado == AppLifecycleState.inactive) _guardarBorrador();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _autoguardado?.cancel();
    _guardarBorrador();
    for (final c in [_diagnostico, _trabajo, _observaciones, _recomendaciones, _pendientes, _receptor, _dni, _cargo]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _inicializar() async {
    try {
      final r = await Future.wait<Object?>([
        Api.i.proyectos(),
        widget.incidenciaId != null ? Api.i.incidencia(widget.incidenciaId!) : Future.value(null),
        Api.i.almacen().catchError((_) => <ArticuloAlmacen>[]),
        Api.i.checklists().catchError((_) => <String, List<String>>{}),
      ]);
      Api.i
          .verificaciones()
          .then((l) {
            if (!mounted) return;
            // Se conservan las respuestas del borrador recuperado; sin cambios del técnico, no cuenta como modificación
            final previas = {for (final v in _verificaciones) v.item: v.resultado};
            final sinCambios = _listo && jsonEncode(_serializar()) == _inicial;
            setState(() => _verificaciones = [for (final item in l) _Verificacion(item)..resultado = previas[item] ?? 'ok']);
            if (sinCambios) _inicial = jsonEncode(_serializar());
          })
          .catchError((_) {});

      _proyectos = r[0] as List<ProyectoOpcion>;
      _articulos = r[2] as List<ArticuloAlmacen>;
      _checklists = r[3] as Map<String, List<String>>;
      final inc = r[1] as Incidencia?;
      if (inc != null) {
        _incidencia = inc;
        _proyectoId = inc.proyecto.id;
        _receptor.text = inc.reportadoPor ?? '';
        // Los equipos con falla de la incidencia ya aparecen en la lista: cada uno con su diagnóstico y resultado
        for (final e in inc.equipos) {
          _equipos.add(
            _EquipoForm()
              ..equipoId = '${e.id}'
              ..codigo = e.codigo
              ..equipo = e.categoria
              ..marca = e.marca ?? ''
              ..modelo = e.modelo ?? ''
              ..serie = e.serie ?? ''
              ..accion = 'reparado'
              ..deIncidencia = true
              ..checklist = _puntosPara(e.categoria),
          );
        }
        final ultima = inc.atenciones.firstOrNull;
        if (ultima != null) {
          _diagnostico.text = ultima.diagnostico;
          // Segunda visita: se parte de lo que quedó pendiente; lo de la visita anterior ya está en su hoja
          _trabajo.text = inc.motivoReprogramacion != null ? 'Continuación. Pendiente: ${inc.motivoReprogramacion}\n' : ultima.actividades;
          _observaciones.text = inc.motivoReprogramacion != null ? '' : ultima.observaciones ?? '';
        }
      }
      _cargarInventario();
    } catch (e) {
      if (mounted) mensaje(context, 'Error', '$e');
    } finally {
      _inicial = jsonEncode(_serializar());
      final b = await Borradores.leer(_claveBorrador);
      if (b != null) {
        _restaurar(b.datos);
        _recuperadoDe = b.fecha;
        _errorEnvio = b.error;
        _pendienteDeEnvio = b.pendienteDeEnvio;
        _ultimoGuardado = jsonEncode(_serializar());
        _cargarInventario();
      }
      _autoguardado = Timer.periodic(const Duration(seconds: 2), (_) => _guardarBorrador());
      if (mounted) setState(() => _listo = true);
    }
  }

  bool get _modificado => _listo && jsonEncode(_serializar()) != _inicial;

  String get _tituloBorrador {
    final inc = _incidencia;
    if (inc != null) return '${inc.codigo} · ${inc.titulo}';
    return widget.etiqueta ?? _proyecto?.nombre ?? 'Hoja de servicio';
  }

  /// Guarda lo llenado en el teléfono (solo si cambió desde el último guardado).
  Future<void> _guardarBorrador() async {
    if (!_listo || _enviada || _pendienteDeEnvio || !_modificado) return;
    final datos = _serializar();
    final texto = jsonEncode(datos);
    if (texto == _ultimoGuardado) return;
    _ultimoGuardado = texto;
    await Borradores.guardar(_claveBorrador, _tituloBorrador, datos);
  }

  /// Descarta el borrador y vuelve a abrir la hoja en blanco.
  Future<void> _descartarBorrador() async {
    final si = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('¿Descartar el borrador?'),
        content: const Text('Se borrará lo que había llenado en esta hoja, incluidas sus fotos.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Cancelar')),
          TextButton(
            onPressed: () => Navigator.pop(c, true),
            child: const Text('Descartar', style: TextStyle(color: C.danger)),
          ),
        ],
      ),
    );
    if (si != true || !mounted) return;
    _enviada = true;
    await Borradores.borrar(_claveBorrador);
    if (mounted) Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => widget));
  }

  Map<String, dynamic> _archivoJson(ArchivoLocal a) => {
    'ruta': a.ruta,
    'nombre': a.nombre,
    'mime': a.mime,
    'es_imagen': a.esImagen,
    'categoria': a.categoria,
    'descripcion': a.descripcion,
  };

  ArchivoLocal _archivoDe(Map a) => ArchivoLocal(
    ruta: '${a['ruta']}',
    nombre: '${a['nombre']}',
    mime: '${a['mime']}',
    esImagen: a['es_imagen'] == true,
    categoria: '${a['categoria'] ?? 'foto'}',
    descripcion: '${a['descripcion'] ?? ''}',
  );

  /// Todo lo que el técnico lleva llenado, para guardarlo en el teléfono.
  Map<String, dynamic> _serializar() => {
    'uuid': _uuid,
    'proyecto_id': _proyectoId,
    'garantia_id': _garantiaId,
    'tipo': _tipo,
    'llegada': horaDe(_llegada),
    'salida': _salida == null ? null : horaDe(_salida!),
    'resultado': _resultado,
    'proxima_visita': fechaIso(_proximaVisita),
    'diagnostico': _diagnostico.text,
    'trabajo': _trabajo.text,
    'observaciones': _observaciones.text,
    'recomendaciones': _recomendaciones.text,
    'pendientes': _pendientes.text,
    'receptor': _receptor.text,
    'dni': _dni.text,
    'cargo': _cargo.text,
    'verificaciones': {for (final v in _verificaciones) v.item: v.resultado},
    'evaluacion': _evaluacion,
    'equipos': [
      for (final e in _equipos)
        {
          'equipo_id': e.equipoId,
          'codigo': e.codigo,
          'equipo': e.equipo,
          'marca': e.marca,
          'modelo': e.modelo,
          'serie': e.serie,
          'accion': e.accion,
          'estado_final': e.estadoFinal,
          'observacion': e.observacion,
          'de_incidencia': e.deIncidencia,
          'diagnostico': e.diagnostico,
          'resultado': e.resultado,
          'fotos': [for (final f in e.fotos) _archivoJson(f)],
          'checklist': [
            for (final p in e.checklist) {'item': p.item, 'resultado': p.resultado, 'nota': p.nota},
          ],
        },
    ],
    'mediciones': [
      for (final m in _mediciones) {'parametro': m.parametro, 'unidad': m.unidad, 'valor': m.valor, 'fija': m.fija},
    ],
    'materiales': [
      for (final m in _materiales) {'articulo_id': m.articuloId, 'descripcion': m.descripcion, 'cantidad': m.cantidad, 'unidad': m.unidad},
    ],
    'fotos': [for (final f in _fotos) _archivoJson(f)],
    'actas': [for (final a in _actas) _archivoJson(a)],
  };

  /// Vuelve a llenar el formulario con un borrador guardado (las fotos que ya no existan se omiten).
  void _restaurar(Map<String, dynamic> d) {
    TimeOfDay? hora(Object? v) {
      final partes = '$v'.split(':');
      return partes.length == 2 ? TimeOfDay(hour: int.tryParse(partes[0]) ?? 0, minute: int.tryParse(partes[1]) ?? 0) : null;
    }

    List<ArchivoLocal> archivos(Object? l) => [
      for (final a in (l as List? ?? []).cast<Map>())
        if (File('${a['ruta']}').existsSync()) _archivoDe(a),
    ];

    _uuid = '${d['uuid'] ?? _uuid}';
    _proyectoId = d['proyecto_id'] as int? ?? _proyectoId;
    _garantiaId = d['garantia_id'] as int?;
    _tipo = '${d['tipo'] ?? _tipo}';
    _llegada = hora(d['llegada']) ?? _llegada;
    _salida = hora(d['salida']);
    _resultado = '${d['resultado'] ?? _resultado}';
    _proximaVisita = DateTime.tryParse('${d['proxima_visita']}') ?? _proximaVisita;
    _diagnostico.text = '${d['diagnostico'] ?? ''}';
    _trabajo.text = '${d['trabajo'] ?? ''}';
    _observaciones.text = '${d['observaciones'] ?? ''}';
    _recomendaciones.text = '${d['recomendaciones'] ?? ''}';
    _pendientes.text = '${d['pendientes'] ?? ''}';
    _receptor.text = '${d['receptor'] ?? ''}';
    _dni.text = '${d['dni'] ?? ''}';
    _cargo.text = '${d['cargo'] ?? ''}';

    final verif = Map<String, dynamic>.from(d['verificaciones'] as Map? ?? {});
    if (verif.isNotEmpty) {
      _verificaciones = [for (final e in verif.entries) _Verificacion(e.key)..resultado = '${e.value}'];
    }
    _evaluacion.addAll(Map<String, String>.from(d['evaluacion'] as Map? ?? {}));

    _equipos
      ..clear()
      ..addAll([
        for (final e in (d['equipos'] as List? ?? []).cast<Map>())
          _EquipoForm()
            ..equipoId = '${e['equipo_id'] ?? ''}'
            ..codigo = '${e['codigo'] ?? ''}'
            ..equipo = '${e['equipo'] ?? ''}'
            ..marca = '${e['marca'] ?? ''}'
            ..modelo = '${e['modelo'] ?? ''}'
            ..serie = '${e['serie'] ?? ''}'
            ..accion = '${e['accion'] ?? 'revisado'}'
            ..estadoFinal = '${e['estado_final'] ?? 'operativo'}'
            ..observacion = '${e['observacion'] ?? ''}'
            ..deIncidencia = e['de_incidencia'] == true
            ..diagnostico = '${e['diagnostico'] ?? ''}'
            ..resultado = '${e['resultado'] ?? ''}'
            ..fotos.addAll(archivos(e['fotos']))
            ..checklist = [
              for (final p in (e['checklist'] as List? ?? []).cast<Map>())
                _Punto('${p['item']}')
                  ..resultado = '${p['resultado'] ?? ''}'
                  ..nota = '${p['nota'] ?? ''}',
            ],
      ]);
    _mediciones
      ..clear()
      ..addAll([
        for (final m in (d['mediciones'] as List? ?? []).cast<Map>())
          _MedicionForm('${m['parametro'] ?? ''}', '${m['unidad'] ?? ''}', fija: m['fija'] == true)..valor = '${m['valor'] ?? ''}',
      ]);
    _materiales
      ..clear()
      ..addAll([
        for (final m in (d['materiales'] as List? ?? []).cast<Map>())
          _MaterialForm(articuloId: '${m['articulo_id'] ?? ''}', descripcion: '${m['descripcion'] ?? ''}', unidad: '${m['unidad'] ?? 'und'}')
            ..cantidad = '${m['cantidad'] ?? '1'}',
      ]);
    _fotos
      ..clear()
      ..addAll(archivos(d['fotos']));
    _actas
      ..clear()
      ..addAll(archivos(d['actas']));
  }

  /// Inventario del cliente del proyecto elegido.
  void _cargarInventario() {
    _inventario = [];
    final id = _proyectoId;
    if (id == null) return;
    Api.i
        .equiposDeProyecto(id)
        .then((l) {
          if (mounted && _proyectoId == id) setState(() => _inventario = l);
        })
        .catchError((_) {});
  }

  List<_Punto> _puntosPara(String categoria) => (_checklists[_clave(categoria)] ?? []).map(_Punto.new).toList();

  void _agregarDelInventario(Equipo e) {
    setState(() {
      if (!_equipos.any((x) => x.equipoId == '${e.id}')) {
        _equipos.add(_EquipoForm.inventario(e, _tipo == 'preventivo' ? 'revisado' : 'reparado')..checklist = _puntosPara(e.categoria));
      }
      _verInventario = false;
    });
  }

  Future<void> _escanear() async {
    final equipo = await Navigator.push<Equipo>(context, MaterialPageRoute(builder: (_) => const EscanearPantalla(devolver: true)));
    if (equipo != null) _agregarDelInventario(equipo);
  }

  ProyectoOpcion? get _proyecto => _proyectos.where((p) => p.id == _proyectoId).firstOrNull;

  /// La incidencia tiene equipos con falla: el resultado de la visita sale de cada equipo.
  bool get _porEquipo => _equipos.any((e) => e.deIncidencia);
  int get _equiposPendientes => _equipos.where((e) => e.deIncidencia && _continua.contains(e.resultado)).length;
  int get _equiposReparados => _equipos.where((e) => e.deIncidencia && e.resultado.isNotEmpty && !_continua.contains(e.resultado)).length;
  bool get _requiereVisita => _porEquipo ? _equiposPendientes > 0 : _continua.contains(_resultado);

  Future<void> _elegirHora(bool llegada) async {
    final t = await showTimePicker(context: context, initialTime: (llegada ? _llegada : _salida) ?? TimeOfDay.now());
    if (t != null) setState(() => llegada ? _llegada = t : _salida = t);
  }

  Future<void> _elegirProximaVisita() async {
    final hoy = DateTime.now();
    final d = await showDatePicker(context: context, initialDate: _proximaVisita, firstDate: hoy, lastDate: hoy.add(const Duration(days: 365)));
    if (d != null) setState(() => _proximaVisita = d);
  }

  Future<void> _guardar() async {
    Future<void> falta(String t, String m) => mensaje(context, t, m);
    final inc = _incidencia;

    if (_proyectoId == null) return falta('Falta el proyecto', 'Seleccione el proyecto atendido.');
    if (inc != null && !_porEquipo && _diagnostico.text.trim().isEmpty) {
      return falta('Falta el diagnóstico', 'Indique qué encontró y la causa de la falla.');
    }
    for (final e in _equipos.where((e) => e.deIncidencia)) {
      if (e.diagnostico.trim().isEmpty || e.resultado.isEmpty) {
        return falta('Equipo ${e.codigo}', 'Indique el diagnóstico de este equipo y cómo quedó.');
      }
    }
    if (_trabajo.text.trim().isEmpty) return falta('Faltan datos', 'Describa el trabajo realizado.');
    for (final m in _materiales.where((m) => m.articuloId.isNotEmpty)) {
      final stock = _articulos.where((a) => '${a.id}' == m.articuloId).firstOrNull?.stock ?? 0;
      if ((double.tryParse(m.cantidad.replaceAll(',', '.')) ?? 0) > stock) {
        return falta('Stock insuficiente', 'No hay suficiente «${m.descripcion}» en el almacén.');
      }
    }
    if (inc != null && _requiereVisita && _pendientes.text.trim().isEmpty) {
      return falta('¿Qué queda pendiente?', 'Indique qué falta hacer en la próxima visita.');
    }

    setState(() => _enviando = true);
    final f = Formulario();
    try {
      f.campo('uuid_cliente', _uuid);
      if (inc != null) f.campo('incidencia_id', inc.id);
      f.campo('mantenimiento_id', widget.mantenimientoId);
      f.campo('proyecto_id', _proyectoId);
      if (inc == null) f.campo('garantia_id', _garantiaId);
      f.campo('tipo_servicio', _tipo);
      f.campo('fecha', fechaIso(DateTime.now()));
      f.campo('hora_llegada', horaDe(_llegada));
      if (_salida != null) f.campo('hora_salida', horaDe(_salida!));
      if (inc != null) {
        f.campo('diagnostico', _diagnostico.text.trim());
        // Con equipos de la incidencia, el servidor calcula el resultado a partir de cada equipo
        if (!_porEquipo) f.campo('resultado', _resultado);
        if (_requiereVisita) {
          f.campo('pendientes', _pendientes.text.trim());
          f.campo('proxima_visita', fechaIso(_proximaVisita));
        }
      }
      for (final (i, v) in _verificaciones.indexed) {
        f.campo('verificaciones[$i][item]', v.item);
        f.campo('verificaciones[$i][resultado]', v.resultado);
      }
      for (final (i, (item, obs)) in _evaluacion.entries.where((e) => e.value.trim().isNotEmpty).map((e) => (e.key, e.value)).indexed) {
        f.campo('evaluacion[$i][item]', item);
        f.campo('evaluacion[$i][observacion]', obs.trim());
      }
      for (final (i, m) in _mediciones.where((m) => m.parametro.trim().isNotEmpty && m.valor.trim().isNotEmpty).indexed) {
        f.campo('mediciones[$i][parametro]', m.parametro.trim());
        f.campo('mediciones[$i][valor]', m.valor.trim());
        f.campo('mediciones[$i][unidad]', m.unidad.trim());
      }
      f.campo('trabajo_realizado', _trabajo.text.trim());
      f.campo('observaciones', _observaciones.text.trim());
      f.campo('recomendaciones', _recomendaciones.text.trim());

      for (final (i, e) in _equipos.where((e) => e.equipo.trim().isNotEmpty || e.equipoId.isNotEmpty).indexed) {
        f.campo('equipos[$i][equipo_id]', e.equipoId);
        f.campo('equipos[$i][equipo]', e.equipo.trim());
        f.campo('equipos[$i][marca]', e.marca.trim());
        f.campo('equipos[$i][modelo]', e.modelo.trim());
        f.campo('equipos[$i][serie]', e.serie.trim());
        f.campo('equipos[$i][accion]', e.accion);
        f.campo('equipos[$i][estado_final]', e.estadoFinal);
        f.campo('equipos[$i][observacion]', e.observacion.trim());
        f.campo('equipos[$i][diagnostico]', e.diagnostico.trim());
        f.campo('equipos[$i][resultado]', e.resultado);
        for (final (j, foto) in e.fotos.indexed) {
          f.archivo('equipos[$i][fotos][$j]', foto);
        }
        for (final (j, p) in e.checklist.where((p) => p.resultado.isNotEmpty).indexed) {
          f.campo('equipos[$i][checklist][$j][item]', p.item);
          f.campo('equipos[$i][checklist][$j][resultado]', p.resultado);
          f.campo('equipos[$i][checklist][$j][nota]', p.nota.trim());
        }
      }
      for (final (i, m) in _materiales.where((m) => m.descripcion.trim().isNotEmpty).indexed) {
        f.campo('materiales[$i][articulo_id]', m.articuloId);
        f.campo('materiales[$i][descripcion]', m.descripcion.trim());
        f.campo('materiales[$i][cantidad]', m.cantidad.replaceAll(',', '.'));
        f.campo('materiales[$i][unidad]', m.unidad.trim());
      }
      for (final (i, foto) in _fotos.indexed) {
        f.archivo('fotos[$i]', foto);
        f.campo('fotos_descripcion[$i]', foto.descripcion.trim());
      }
      for (final (i, a) in _actas.indexed) {
        f.archivo('actas[$i]', a);
      }
      f.campo('receptor_nombre', _receptor.text.trim());
      f.campo('receptor_dni', _dni.text.trim());
      f.campo('receptor_cargo', _cargo.text.trim());

      final pos = await _ubicacion();
      if (pos != null) {
        f.campo('latitud', pos.latitude);
        f.campo('longitud', pos.longitude);
      }

      final hoja = await Api.i.crearHoja(f);
      _enviada = true;
      await Borradores.borrar(_claveBorrador);
      if (!mounted) return;
      await mensaje(
        context,
        'Hoja ${hoja.numero} guardada',
        [
          if (hoja.proximaVisita != null) 'La incidencia continúa: próxima visita el ${fecha(hoja.proximaVisita, hora: false)}.',
          hoja.tieneActa ? 'El acta de conformidad quedó registrada.' : 'Falta subir el acta firmada por el cliente (puede hacerlo desde la hoja).',
        ].join('\n\n'),
      );
      if (mounted) Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => HojaPantalla(id: hoja.id)));
    } on ApiError catch (e) {
      if (e.status == 0) {
        // Sin señal: la hoja queda en el teléfono y se envía sola cuando vuelva la conexión
        _pendienteDeEnvio = true;
        await Borradores.guardar(_claveBorrador, _tituloBorrador, _serializar(), envio: f.toJson());
        if (!mounted) return;
        await mensaje(
          context,
          'Sin conexión',
          'La hoja quedó guardada en el teléfono y se enviará sola cuando vuelva la señal. '
              'También puede enviarla desde «Mis incidencias» con «Enviar ahora».',
        );
        if (mounted) Navigator.pop(context);
      } else if (mounted) {
        mensaje(context, 'No se pudo guardar', '$e');
      }
    } catch (e) {
      if (mounted) mensaje(context, 'No se pudo guardar', '$e');
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  /// Ubicación de la atención (si el técnico da permiso); no impide guardar si falla.
  Future<Position?> _ubicacion() async {
    try {
      var permiso = await Geolocator.checkPermission();
      if (permiso == LocationPermission.denied) permiso = await Geolocator.requestPermission();
      if (permiso == LocationPermission.denied || permiso == LocationPermission.deniedForever) return null;
      return await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.medium, timeLimit: Duration(seconds: 10)),
      );
    } catch (_) {
      return null;
    }
  }

  // ── Interfaz ──────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final titulo = AppBar(title: const Text('Nueva hoja de servicio'));
    if (!_listo) return Scaffold(appBar: titulo, body: const Cargando());
    final inc = _incidencia;

    return PopScope(
      onPopInvokedWithResult: (salio, _) {
        if (!salio || _enviada || !_modificado) return;
        _guardarBorrador();
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('La hoja quedó guardada como borrador en el teléfono. Al volver, la encontrará como la dejó.')));
      },
      child: Scaffold(
        appBar: titulo,
        body: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 48),
          children: [
            if (_pendienteDeEnvio)
              const Aviso(
                tono: 'blue',
                children: [
                  Text('Pendiente de envío', style: TextStyle(fontWeight: FontWeight.w700)),
                  Text('Esta hoja se guardó sin señal y se enviará sola cuando vuelva la conexión.', style: TextStyle(fontSize: 13)),
                ],
              )
            else if (_errorEnvio != null)
              Aviso(
                tono: 'red',
                children: [
                  const Text('El servidor no aceptó la hoja', style: TextStyle(fontWeight: FontWeight.w700)),
                  Text('$_errorEnvio Corrija y vuelva a guardar.', style: const TextStyle(fontSize: 13)),
                ],
              )
            else if (_recuperadoDe != null)
              Aviso(
                tono: 'green',
                children: [
                  Text('Se recuperó el borrador del ${fecha(_recuperadoDe!.toIso8601String())}', style: const TextStyle(fontWeight: FontWeight.w700)),
                  const Text('Lo que había llenado se guardó en el teléfono.', style: TextStyle(fontSize: 13)),
                  TextButton(
                    style: TextButton.styleFrom(padding: EdgeInsets.zero, foregroundColor: C.danger),
                    onPressed: _descartarBorrador,
                    child: const Text('Descartar y empezar de nuevo'),
                  ),
                ],
              ),
            if (widget.mantenimientoId != null)
              Aviso(
                tono: 'blue',
                children: [
                  Text(widget.etiqueta ?? 'Mantenimiento programado', style: const TextStyle(fontWeight: FontWeight.w700)),
                  const Text('Al guardar, la visita queda como realizada.', style: TextStyle(fontSize: 13)),
                ],
              ),
            _seccionProyecto(inc),
            _seccionVerificaciones(),
            _seccionEquipos(),
            _seccionTrabajo(inc),
            _seccionResultado(inc),
            _seccionConformidad(),
            Boton(_actas.isNotEmpty ? 'Guardar hoja con acta' : 'Guardar hoja de servicio', cargando: _enviando, onPressed: _guardar),
            const Padding(
              padding: EdgeInsets.only(top: 8),
              child: Text(
                'Lo que llena se guarda solo en el teléfono mientras escribe.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 12, color: C.textMuted),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _seccionProyecto(Incidencia? inc) {
    final proyecto = _proyecto;
    final filtrados = _proyectos.where((p) => '${p.nombre} ${p.entidad} ${p.codigo}'.toLowerCase().contains(_buscar.toLowerCase())).take(8);
    return Tarjeta(
      titulo: '1. Proyecto atendido',
      children: [
        if (inc != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${inc.codigo} · ${inc.titulo}', style: _fuerte),
                Text(inc.proyecto.entidad, style: _tenue),
              ],
            ),
          )
        else if (proyecto != null)
          InkWell(
            onTap: widget.mantenimientoId != null
                ? null
                : () => setState(() {
                    _proyectoId = null;
                    _garantiaId = null;
                    _cargarInventario();
                  }),
            child: Container(
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: C.brandSoft, borderRadius: BorderRadius.circular(10)),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(proyecto.nombre, style: _fuerte),
                        Text(proyecto.entidad, style: _tenue),
                      ],
                    ),
                  ),
                  if (widget.mantenimientoId == null)
                    const Text(
                      'Cambiar',
                      style: TextStyle(color: C.brand, fontWeight: FontWeight.w700),
                    ),
                ],
              ),
            ),
          )
        else ...[
          TextField(
            onChanged: (t) => setState(() => _buscar = t),
            decoration: const InputDecoration(hintText: 'Buscar proyecto o cliente…'),
          ),
          for (final p in filtrados)
            _OpcionLista(
              p.nombre,
              '${p.codigo} · ${p.entidad}',
              onTap: () {
                setState(() => _proyectoId = p.id);
                _cargarInventario();
              },
            ),
          const SizedBox(height: 12),
        ],
        if (inc == null && proyecto != null && proyecto.garantias.isNotEmpty) ...[
          const Text('Garantía', style: _label),
          const SizedBox(height: 8),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              for (final g in proyecto.garantias)
                Opcion(g.texto, activo: _garantiaId == g.id, onTap: () => setState(() => _garantiaId = _garantiaId == g.id ? null : g.id)),
            ],
          ),
          const SizedBox(height: 12),
        ],
        const Text('Tipo de servicio', style: _label),
        const SizedBox(height: 8),
        Wrap(
          spacing: 6,
          runSpacing: 6,
          children: [for (final (valor, texto) in _tipos) Opcion(texto, activo: _tipo == valor, onTap: () => setState(() => _tipo = valor))],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(child: _CampoHora('Llegada *', horaDe(_llegada), () => _elegirHora(true))),
            const SizedBox(width: 10),
            Expanded(child: _CampoHora('Salida', _salida == null ? '—' : horaDe(_salida!), () => _elegirHora(false))),
          ],
        ),
      ],
    );
  }

  Widget _seccionVerificaciones() => Tarjeta(
    titulo: '2. Evaluación preliminar',
    children: [
      // Condiciones del sitio: dónde está el equipo, aire acondicionado, material inflamable y acceso
      for (final (item, ejemplo) in _evaluacionSitio) ...[
        Text(item, style: _label),
        const SizedBox(height: 6),
        _Texto(_evaluacion[item]!, ejemplo, (t) => _evaluacion[item] = t, lineas: 2),
      ],
      if (_verificaciones.isNotEmpty) ...[const SizedBox(height: 6), const Text('Verificaciones de seguridad', style: _label)],
      for (final v in _verificaciones)
        _PuntoControl(
          v.item,
          opciones: const [('ok', 'Sí', Color(0xFF059669)), ('no', 'No', Color(0xFFDC2626)), ('na', 'N/A', Color(0xFF64748B))],
          valor: v.resultado,
          onChanged: (r) => setState(() => v.resultado = r),
        ),
    ],
  );

  Widget _seccionEquipos() {
    final disponibles = _inventario.where((x) => !_equipos.any((e) => e.equipoId == '${x.id}')).toList();
    return Tarjeta(
      titulo: '3. Equipos intervenidos (${_equipos.length})',
      children: [
        for (final e in _equipos) _bloqueEquipo(e),
        if (_verInventario)
          _Desplegable(
            children: [
              for (final x in disponibles) _OpcionLista('${x.codigo} · ${x.nombre}', x.ubicacion, onTap: () => _agregarDelInventario(x)),
              if (_inventario.isEmpty)
                const Padding(
                  padding: EdgeInsets.all(10),
                  child: Text('Este cliente aún no tiene equipos registrados.', style: _tenue),
                ),
            ],
          ),
        Row(
          children: [
            Expanded(
              child: Boton('Escanear', icono: Icons.qr_code_scanner, variante: Variante.secondary, onPressed: _escanear),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Boton(
                _verInventario ? 'Cerrar lista' : 'Inventario (${_inventario.length})',
                variante: Variante.secondary,
                onPressed: _proyectoId == null ? null : () => setState(() => _verInventario = !_verInventario),
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Boton('Equipo nuevo', icono: Icons.add, variante: Variante.secondary, onPressed: () => setState(() => _equipos.add(_EquipoForm()))),
      ],
    );
  }

  Widget _bloqueEquipo(_EquipoForm e) {
    final delInventario = e.equipoId.isNotEmpty;
    return Container(
      key: ObjectKey(e),
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: delInventario ? const Color(0xFFF8FBFF) : Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: delInventario ? const Color(0xFFBFDBFE) : C.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: delInventario
                    ? Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            e.deIncidencia ? '${e.codigo} · equipo con falla' : '${e.codigo} · del inventario',
                            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: e.deIncidencia ? C.danger : C.brand),
                          ),
                          Text([e.equipo, e.marca, e.modelo].where((t) => t.isNotEmpty).join(' '), style: _fuerte),
                          if (e.serie.isNotEmpty) Text('S/N ${e.serie}', style: _tenue),
                        ],
                      )
                    : Focus(
                        onFocusChange: (f) {
                          if (!f) setState(() => e.checklist = _puntosPara(e.equipo));
                        },
                        child: _Texto(e.equipo, 'Equipo (ej. Cámara PTZ)', (t) => e.equipo = t),
                      ),
              ),
              // Los equipos de la incidencia no se quitan: cada uno debe tener su resultado
              if (!e.deIncidencia)
                IconButton(
                  onPressed: () => setState(() => _equipos.remove(e)),
                  icon: const Icon(Icons.close, color: C.danger),
                  visualDensity: VisualDensity.compact,
                ),
            ],
          ),
          if (e.deIncidencia) _fallaDelEquipo(e),
          if (!delInventario) ...[
            Row(
              children: [
                Expanded(child: _Texto(e.marca, 'Marca', (t) => e.marca = t)),
                const SizedBox(width: 8),
                Expanded(child: _Texto(e.modelo, 'Modelo', (t) => e.modelo = t)),
              ],
            ),
            _Texto(e.serie, 'N° de serie (lo registra en el inventario)', (t) => e.serie = t, mayusculas: true),
          ],
          const SizedBox(height: 4),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [for (final (v, t) in _acciones) Opcion(t, activo: e.accion == v, onTap: () => setState(() => e.accion = v))],
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [for (final (v, t) in _estados) Opcion(t, activo: e.estadoFinal == v, onTap: () => setState(() => e.estadoFinal = v))],
          ),
          const SizedBox(height: 8),
          _Texto(e.observacion, e.accion == 'cambio_componente' ? 'Componente cambiado (ej. disco duro)' : 'Observación', (t) => e.observacion = t),
          if (e.checklist.isNotEmpty)
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(color: const Color(0xFFF8FAFC), borderRadius: BorderRadius.circular(10)),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Checklist de mantenimiento', style: _label),
                  for (final p in e.checklist)
                    _PuntoControl(
                      p.item,
                      key: ObjectKey(p),
                      opciones: _puntos,
                      valor: p.resultado,
                      onChanged: (r) => setState(() => p.resultado = r),
                      nota: (p.resultado == 'observado' || p.resultado == 'falla') ? _Texto(p.nota, '¿Qué se encontró?', (t) => p.nota = t) : null,
                    ),
                  TextButton(
                    style: TextButton.styleFrom(padding: EdgeInsets.zero),
                    onPressed: () => setState(() {
                      for (final p in e.checklist.where((p) => p.resultado.isEmpty)) {
                        p.resultado = 'ok';
                      }
                    }),
                    child: const Text('Marcar pendientes como OK', style: TextStyle(fontWeight: FontWeight.w700)),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _seccionTrabajo(Incidencia? inc) {
    void agregarMediciones(List<_MedicionForm> m) => setState(() => _mediciones.addAll(m));
    return Tarjeta(
      titulo: inc != null ? '4. Diagnóstico y trabajo realizado' : '4. Trabajo realizado',
      children: [
        if (inc != null && _porEquipo)
          Campo(
            'Diagnóstico general (opcional)',
            controller: _diagnostico,
            multilinea: true,
            hint: 'El diagnóstico de cada equipo va en su ficha, arriba.',
          )
        else if (inc != null)
          Campo('Diagnóstico', controller: _diagnostico, multilinea: true, requerido: true, hint: '¿Qué encontró? ¿Cuál es la causa?'),
        Campo('Trabajo realizado', controller: _trabajo, multilinea: true, requerido: true),

        const Text('Mediciones (opcional)', style: _label),
        const SizedBox(height: 8),
        for (final m in _mediciones)
          Row(
            key: ObjectKey(m),
            children: [
              Expanded(
                child: m.fija
                    ? Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: Text(m.parametro, style: const TextStyle(fontSize: 14, color: C.text)),
                      )
                    : _Texto(m.parametro, 'Parámetro', (t) => m.parametro = t),
              ),
              const SizedBox(width: 6),
              SizedBox(width: 90, child: _Texto(m.valor, 'Valor', (t) => m.valor = t, teclado: const TextInputType.numberWithOptions(decimal: true))),
              const SizedBox(width: 6),
              m.fija
                  ? SizedBox(
                      width: 30,
                      child: Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: Text(m.unidad, style: _tenue),
                      ),
                    )
                  : SizedBox(width: 58, child: _Texto(m.unidad, 'Unid.', (t) => m.unidad = t)),
              IconButton(
                onPressed: () => setState(() => _mediciones.remove(m)),
                icon: const Icon(Icons.close, color: C.danger),
                visualDensity: VisualDensity.compact,
              ),
            ],
          ),
        Wrap(
          spacing: 6,
          runSpacing: 6,
          children: [
            Opcion('+ Tensiones', activo: false, onTap: () => _elegirSistema('tension')),
            Opcion('+ Corrientes (amperaje)', activo: false, onTap: () => _elegirSistema('corriente')),
            Opcion('+ Temperatura', activo: false, onTap: () => agregarMediciones([_MedicionForm('Temperatura del ambiente', '°C', fija: true)])),
            Opcion('+ Otra', activo: false, onTap: () => agregarMediciones([_MedicionForm('', '')])),
          ],
        ),
        const SizedBox(height: 16),

        const Text('Materiales / repuestos', style: _label),
        const SizedBox(height: 8),
        for (final m in _materiales)
          Row(
            key: ObjectKey(m),
            children: [
              Expanded(child: _Texto(m.descripcion, 'Descripción', (t) => m.descripcion = t, editable: m.articuloId.isEmpty)),
              const SizedBox(width: 6),
              SizedBox(
                width: 58,
                child: _Texto(m.cantidad, '', (t) => m.cantidad = t, teclado: const TextInputType.numberWithOptions(decimal: true)),
              ),
              const SizedBox(width: 6),
              SizedBox(width: 62, child: _Texto(m.unidad, '', (t) => m.unidad = t)),
              IconButton(
                onPressed: () => setState(() => _materiales.remove(m)),
                icon: const Icon(Icons.close, color: C.danger),
                visualDensity: VisualDensity.compact,
              ),
            ],
          ),
        if (_verAlmacen)
          _Desplegable(
            children: [
              for (final a in _articulos)
                _OpcionLista(
                  a.nombre,
                  '${a.codigo} · stock ${a.stockTexto} ${a.unidad}',
                  alerta: a.stock <= 0,
                  onTap: () {
                    setState(() {
                      _materiales.add(_MaterialForm(articuloId: '${a.id}', descripcion: a.nombre, unidad: a.unidad));
                      _verAlmacen = false;
                    });
                  },
                ),
              if (_articulos.isEmpty)
                const Padding(
                  padding: EdgeInsets.all(10),
                  child: Text('No hay artículos en el almacén.', style: _tenue),
                ),
            ],
          ),
        Row(
          children: [
            Expanded(
              child: Boton(
                _verAlmacen ? 'Cerrar almacén' : 'Del almacén',
                icono: Icons.inventory_2_outlined,
                variante: Variante.secondary,
                onPressed: () => setState(() => _verAlmacen = !_verAlmacen),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Boton(
                'Otro material',
                icono: Icons.add,
                variante: Variante.secondary,
                onPressed: () => setState(() => _materiales.add(_MaterialForm())),
              ),
            ),
          ],
        ),
        if (_materiales.any((m) => m.articuloId.isNotEmpty))
          const Padding(
            padding: EdgeInsets.only(top: 6),
            child: Text('Los materiales del almacén se descuentan de su stock al guardar.', style: _tenue),
          ),
        const SizedBox(height: 16),

        Text('Fotos (${_fotos.length})', style: _label),
        const SizedBox(height: 8),
        for (final f in _fotos)
          Padding(
            key: ObjectKey(f),
            padding: const EdgeInsets.only(bottom: 8),
            child: Row(
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: Image.file(File(f.ruta), width: 64, height: 64, fit: BoxFit.cover),
                ),
                const SizedBox(width: 8),
                Expanded(child: _Texto(f.descripcion, 'Leyenda (ej. Ubicación del UPS)', (t) => f.descripcion = t)),
                IconButton(
                  onPressed: () => setState(() => _fotos.remove(f)),
                  icon: const Icon(Icons.close, color: C.danger),
                  visualDensity: VisualDensity.compact,
                ),
              ],
            ),
          ),
        Row(
          children: [
            Expanded(
              child: Boton('Cámara', icono: Icons.photo_camera_outlined, variante: Variante.secondary, onPressed: () => _agregar(_fotos, tomarFoto)),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Boton(
                'Galería',
                icono: Icons.photo_library_outlined,
                variante: Variante.secondary,
                onPressed: () => _agregar(_fotos, elegirDeGaleria),
              ),
            ),
          ],
        ),
        if (_fotos.isNotEmpty)
          const Padding(
            padding: EdgeInsets.only(top: 6),
            child: Text('La leyenda sale debajo de cada foto en la hoja impresa.', style: _tenue),
          ),
      ],
    );
  }

  /// Pregunta si la tensión (o la corriente) es monofásica o trifásica y agrega sus puntos de medición.
  Future<void> _elegirSistema(String tipo) async {
    final nombre = tipo == 'tension' ? 'Tensión' : 'Corriente';
    final sistema = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (c) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(tipo == 'tension' ? '¿Qué sistema tiene la tensión?' : '¿Qué sistema tiene la corriente?', style: _fuerte),
            const SizedBox(height: 8),
            for (final (valor, texto, puntos) in [
              ('monofasico', 'Monofásico', _puntosMedicion[tipo]!['monofasico']!),
              ('trifasico', 'Trifásico', _puntosMedicion[tipo]!['trifasico']!),
            ])
              ListTile(
                leading: Icon(valor == 'monofasico' ? Icons.power_outlined : Icons.electrical_services, color: C.brand),
                title: Text(texto, style: const TextStyle(fontWeight: FontWeight.w700)),
                subtitle: Text(puntos.join(' · ')),
                onTap: () => Navigator.pop(c, valor),
              ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
    if (sistema == null || !mounted) return;

    final sufijo = sistema == 'monofasico' ? 'monofásica' : 'trifásica';
    setState(() {
      // Reemplaza las mediciones vacías del otro sistema
      _mediciones.removeWhere((m) => m.parametro.startsWith(nombre) && m.valor.trim().isEmpty);
      _mediciones.addAll([
        for (final p in _puntosMedicion[tipo]![sistema]!) _MedicionForm('$nombre $sufijo $p', tipo == 'tension' ? 'V' : 'A', fija: true),
      ]);
    });
  }

  Future<void> _agregar(List<ArchivoLocal> lista, Future<List<ArchivoLocal>> Function() fuente) async {
    final nuevos = await fuente();
    // Se copian a la carpeta del borrador: la cámara las deja en una carpeta temporal
    final conservados = [for (final a in nuevos) await Borradores.conservar(_claveBorrador, a)];
    if (conservados.isNotEmpty && mounted) setState(() => lista.addAll(conservados));
  }

  Widget _seccionResultado(Incidencia? inc) {
    final manana = DateUtils.dateOnly(DateTime.now().add(const Duration(days: 1)));
    return Tarjeta(
      titulo: '5. Resultado de la visita',
      children: [
        if (inc != null && _porEquipo) ...[
          _resumenPorEquipo(),
          if (_equiposPendientes > 0) _pendientesYProximaVisita(manana, 'Indique qué falta en esos equipos y cuándo vuelve.'),
        ] else if (inc != null) ...[
          for (final (valor, texto, icono) in _resultados)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Material(
                color: _resultado == valor ? C.brandSoft : Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                  side: BorderSide(color: _resultado == valor ? C.brand : C.border),
                ),
                child: InkWell(
                  borderRadius: BorderRadius.circular(10),
                  onTap: () => setState(() => _resultado = valor),
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Row(
                      children: [
                        Icon(icono, size: 20, color: _resultado == valor ? C.brand : C.textMuted),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            texto,
                            style: TextStyle(fontWeight: FontWeight.w700, color: _resultado == valor ? C.brand : C.text),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          if (_continua.contains(_resultado))
            _pendientesYProximaVisita(
              manana,
              'Lo de hoy queda guardado en esta hoja. Indique qué falta y cuándo vuelve: la incidencia sigue en proceso.',
            ),
        ],
        Campo('Observaciones', controller: _observaciones, multilinea: true),
        Campo('Recomendaciones al cliente', controller: _recomendaciones, multilinea: true),
      ],
    );
  }

  /// Cómo queda la incidencia según el resultado de cada equipo.
  Widget _resumenPorEquipo() {
    final pend = _equiposPendientes, rep = _equiposReparados;
    final (tono, texto) = switch ((rep, pend)) {
      (0, 0) => ('slate', 'Indique arriba cómo quedó cada equipo.'),
      (_, 0) => ('green', 'Todos los equipos quedaron atendidos: la incidencia pasa a «Resuelta» y se emite el informe técnico de cada equipo.'),
      (0, _) => ('amber', 'Ningún equipo quedó terminado: la incidencia sigue en proceso.'),
      _ => (
        'blue',
        '$rep equipo(s) reparado(s) y $pend pendiente(s): al guardar, esta incidencia queda resuelta y los pendientes pasan a una incidencia nueva con usted como técnico.',
      ),
    };
    return Aviso(
      tono: tono,
      children: [Text(texto, style: const TextStyle(fontSize: 13))],
    );
  }

  Widget _pendientesYProximaVisita(DateTime manana, String ayuda) => Aviso(
    children: [
      Text(ayuda, style: const TextStyle(fontSize: 13)),
      const SizedBox(height: 10),
      DefaultTextStyle.merge(
        style: const TextStyle(color: C.text),
        child: Campo(
          '¿Qué queda pendiente?',
          controller: _pendientes,
          multilinea: true,
          requerido: true,
          hint: 'Ej. Cambiar 4 baterías; el repuesto llega el jueves',
        ),
      ),
      _CampoHora('Próxima visita *', fecha(fechaIso(_proximaVisita), hora: false), _elegirProximaVisita),
      const SizedBox(height: 8),
      Wrap(
        spacing: 6,
        runSpacing: 6,
        children: [
          for (final d in [1, 2, 3, 7])
            Opcion(
              d == 1 ? 'Mañana' : 'En $d días',
              activo: DateUtils.isSameDay(_proximaVisita, manana.add(Duration(days: d - 1))),
              onTap: () => setState(() => _proximaVisita = manana.add(Duration(days: d - 1))),
            ),
        ],
      ),
    ],
  );

  /// Diagnóstico, resultado y fotos de un equipo con falla de la incidencia.
  Widget _fallaDelEquipo(_EquipoForm e) => Container(
    margin: const EdgeInsets.only(top: 6, bottom: 8),
    padding: const EdgeInsets.all(10),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(10),
      border: Border.all(color: const Color(0xFFFECACA)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Diagnóstico de este equipo *', style: _label),
        const SizedBox(height: 6),
        _Texto(e.diagnostico, '¿Qué falla tenía? ¿Cuál es la causa?', (t) => e.diagnostico = t, lineas: 3),
        const Text('¿Cómo quedó este equipo? *', style: _label),
        const SizedBox(height: 6),
        Wrap(
          spacing: 6,
          runSpacing: 6,
          children: [
            for (final (valor, texto, _) in _resultados)
              Opcion(
                texto,
                activo: e.resultado == valor,
                colorActivo: const {
                  'resuelto': C.success,
                  'parcial': Color(0xFFD97706),
                  'pendiente_repuesto': Color(0xFF1D4ED8),
                  'no_procede': C.danger,
                }[valor],
                onTap: () => setState(() => e.resultado = valor),
              ),
          ],
        ),
        const SizedBox(height: 10),
        Text('Fotos de este equipo (${e.fotos.length})', style: _label),
        const SizedBox(height: 6),
        if (e.fotos.isNotEmpty)
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              for (final f in e.fotos)
                GestureDetector(
                  onLongPress: () => setState(() => e.fotos.remove(f)),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: Image.file(File(f.ruta), width: 64, height: 64, fit: BoxFit.cover),
                  ),
                ),
            ],
          ),
        if (e.fotos.isNotEmpty)
          const Padding(
            padding: EdgeInsets.only(top: 4),
            child: Text('Mantenga presionada una foto para quitarla.', style: _tenue),
          ),
        const SizedBox(height: 6),
        Row(
          children: [
            Expanded(
              child: Boton('Cámara', icono: Icons.photo_camera_outlined, variante: Variante.secondary, onPressed: () => _agregar(e.fotos, tomarFoto)),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Boton(
                'Galería',
                icono: Icons.photo_library_outlined,
                variante: Variante.secondary,
                onPressed: () => _agregar(e.fotos, elegirDeGaleria),
              ),
            ),
          ],
        ),
      ],
    ),
  );

  Widget _seccionConformidad() => Tarjeta(
    titulo: '6. Conformidad del cliente',
    children: [
      const Padding(
        padding: EdgeInsets.only(bottom: 12),
        child: Text(
          'El cliente firma la hoja impresa (o su propia acta). Tome una foto del acta firmada y súbala aquí o después, desde la hoja.',
          style: _tenue,
        ),
      ),
      Campo('Recibe el servicio (nombre)', controller: _receptor),
      Row(
        children: [
          Expanded(
            child: Campo('DNI', controller: _dni, teclado: TextInputType.number),
          ),
          const SizedBox(width: 10),
          Expanded(child: Campo('Cargo', controller: _cargo)),
        ],
      ),
      for (final a in _actas)
        Container(
          key: ObjectKey(a),
          margin: const EdgeInsets.only(bottom: 8),
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
          decoration: BoxDecoration(color: const Color(0xFFECFDF5), borderRadius: BorderRadius.circular(10)),
          child: Row(
            children: [
              const Icon(Icons.description_outlined, size: 18, color: Color(0xFF065F46)),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  a.nombre,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Color(0xFF065F46), fontWeight: FontWeight.w600),
                ),
              ),
              IconButton(
                onPressed: () => setState(() => _actas.remove(a)),
                icon: const Icon(Icons.close, color: C.danger),
                visualDensity: VisualDensity.compact,
              ),
            ],
          ),
        ),
      Row(
        children: [
          Expanded(
            child: Boton(
              'Foto del acta',
              icono: Icons.photo_camera_outlined,
              variante: Variante.secondary,
              onPressed: () => _agregar(_actas, tomarFoto),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Boton('Archivo / PDF', icono: Icons.attach_file, variante: Variante.secondary, onPressed: () => _agregar(_actas, elegirDocumento)),
          ),
        ],
      ),
    ],
  );
}

const _fuerte = TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: C.text);
const _tenue = TextStyle(fontSize: 13, color: C.textMuted);
const _label = TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: C.text);

/// Campo de texto de las listas dinámicas (equipos, mediciones, materiales, fotos).
class _Texto extends StatelessWidget {
  final String valor, hint;
  final ValueChanged<String> onChanged;
  final bool editable, mayusculas;
  final TextInputType? teclado;
  final int lineas;
  const _Texto(this.valor, this.hint, this.onChanged, {this.editable = true, this.mayusculas = false, this.teclado, this.lineas = 1});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: TextFormField(
      initialValue: valor,
      onChanged: onChanged,
      readOnly: !editable,
      keyboardType: lineas > 1 ? TextInputType.multiline : teclado,
      minLines: lineas,
      maxLines: lineas > 1 ? 8 : 1,
      textCapitalization: mayusculas ? TextCapitalization.characters : TextCapitalization.none,
      style: const TextStyle(fontSize: 14),
      decoration: InputDecoration(hintText: hint, fillColor: editable ? Colors.white : C.brandSoft),
    ),
  );
}

class _CampoHora extends StatelessWidget {
  final String label, valor;
  final VoidCallback onTap;
  const _CampoHora(this.label, this.valor, this.onTap);

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(label, style: _label),
      const SizedBox(height: 6),
      InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: InputDecorator(
          decoration: const InputDecoration(suffixIcon: Icon(Icons.schedule, size: 20)),
          child: Text(valor, style: const TextStyle(fontSize: 15, color: C.text)),
        ),
      ),
    ],
  );
}

/// Punto de control con botones excluyentes (OK / Obs. / Falla / N/A o Sí / No / N/A).
class _PuntoControl extends StatelessWidget {
  final String item, valor;
  final List<(String, String, Color)> opciones;
  final ValueChanged<String> onChanged;
  final Widget? nota;
  const _PuntoControl(this.item, {super.key, required this.opciones, required this.valor, required this.onChanged, this.nota});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 8),
    decoration: const BoxDecoration(
      border: Border(top: BorderSide(color: C.border)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(item, style: const TextStyle(color: C.text, fontSize: 13)),
        const SizedBox(height: 6),
        Row(
          children: [
            for (final (i, (v, texto, fondo)) in opciones.indexed) ...[
              if (i > 0) const SizedBox(width: 6),
              Expanded(
                child: InkWell(
                  onTap: () => onChanged(v),
                  borderRadius: BorderRadius.circular(8),
                  child: Container(
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: valor == v ? fondo : Colors.white,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: valor == v ? fondo : C.border),
                    ),
                    child: Text(
                      texto,
                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: valor == v ? Colors.white : C.textMuted),
                    ),
                  ),
                ),
              ),
            ],
          ],
        ),
        if (nota != null) ...[const SizedBox(height: 6), nota!],
      ],
    ),
  );
}

class _Desplegable extends StatelessWidget {
  final List<Widget> children;
  const _Desplegable({required this.children});

  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(maxHeight: 320),
    margin: const EdgeInsets.only(bottom: 10),
    decoration: BoxDecoration(
      border: Border.all(color: C.border),
      borderRadius: BorderRadius.circular(12),
    ),
    child: ListView(shrinkWrap: true, padding: const EdgeInsets.symmetric(horizontal: 10), children: children),
  );
}

class _OpcionLista extends StatelessWidget {
  final String titulo;
  final String? detalle;
  final VoidCallback onTap;
  final bool alerta;
  const _OpcionLista(this.titulo, this.detalle, {required this.onTap, this.alerta = false});

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    child: Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 10),
      decoration: const BoxDecoration(
        border: Border(top: BorderSide(color: C.border)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(titulo, style: _fuerte),
          if (detalle != null) Text(detalle!, style: _tenue.copyWith(color: alerta ? C.danger : null)),
        ],
      ),
    ),
  );
}
