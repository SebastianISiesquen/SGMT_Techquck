import 'dart:convert';
import 'dart:io';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:path_provider/path_provider.dart';

import 'api.dart';
import 'archivos.dart';

/// Hoja de servicio guardada en el teléfono: lo que el técnico lleva llenado (y sus fotos).
/// Si al guardar no había señal, también guarda el envío listo para mandarse después.
class Borrador {
  final String clave, titulo;
  final int usuarioId;
  final DateTime fecha;
  final Map<String, dynamic> datos;

  /// Hoja lista para enviarse (se guardó sin señal); null mientras sigue en edición.
  final Map<String, dynamic>? envio;

  /// Motivo por el que el servidor rechazó el envío (el técnico debe abrirla y corregir).
  final String? error;

  Borrador({required this.clave, required this.titulo, required this.usuarioId, required this.fecha, required this.datos, this.envio, this.error});

  bool get pendienteDeEnvio => envio != null;

  Map<String, dynamic> toJson() => {
    'clave': clave,
    'titulo': titulo,
    'usuario_id': usuarioId,
    'fecha': fecha.toIso8601String(),
    'datos': datos,
    'envio': envio,
    'error': error,
  };

  Borrador.fromJson(Map<String, dynamic> j)
    : clave = j['clave'] as String,
      titulo = j['titulo'] as String? ?? '',
      usuarioId = j['usuario_id'] as int? ?? 0,
      fecha = DateTime.tryParse(j['fecha'] as String? ?? '') ?? DateTime.now(),
      datos = Map<String, dynamic>.from(j['datos'] as Map? ?? {}),
      envio = j['envio'] == null ? null : Map<String, dynamic>.from(j['envio'] as Map),
      error = j['error'] as String?;
}

/// Borradores de hojas de servicio y envíos pendientes, guardados en la memoria del teléfono.
/// No necesitan internet: la hoja no se pierde si se corta la señal, si el técnico sale del
/// formulario o si Android cierra la app (por ejemplo, al abrir la cámara).
class Borradores {
  Borradores._();

  /// Avisa a la bandeja cuando cambian los borradores o los envíos pendientes.
  static final cambios = ValueNotifier<int>(0);

  /// Usuario de la sesión: cada técnico ve y envía solo sus borradores.
  static int usuarioId = 0;

  static bool _enviando = false;

  static Future<Directory> _carpeta([String? sub]) async {
    final base = await getApplicationSupportDirectory();
    final d = Directory('${base.path}/borradores${sub == null ? '' : '/$sub'}');
    if (!await d.exists()) await d.create(recursive: true);
    return d;
  }

  static String _seguro(String clave) => clave.replaceAll(RegExp(r'[^a-zA-Z0-9_-]'), '_');

  static Future<File> _archivo(String clave) async => File('${(await _carpeta()).path}/${_seguro(clave)}.json');

  static Future<void> guardar(String clave, String titulo, Map<String, dynamic> datos, {Map<String, dynamic>? envio}) async {
    final b = Borrador(clave: clave, titulo: titulo, usuarioId: usuarioId, fecha: DateTime.now(), datos: datos, envio: envio);
    await (await _archivo(clave)).writeAsString(jsonEncode(b.toJson()), flush: true);
    cambios.value++;
  }

  static Future<Borrador?> leer(String clave) async {
    try {
      final f = await _archivo(clave);
      if (!await f.exists()) return null;
      final b = Borrador.fromJson(Map<String, dynamic>.from(jsonDecode(await f.readAsString()) as Map));
      return b.usuarioId == usuarioId ? b : null;
    } catch (_) {
      return null;
    }
  }

  static Future<List<Borrador>> todos() async {
    final lista = <Borrador>[];
    await for (final f in (await _carpeta()).list()) {
      if (f is! File || !f.path.endsWith('.json')) continue;
      try {
        final b = Borrador.fromJson(Map<String, dynamic>.from(jsonDecode(await f.readAsString()) as Map));
        if (b.usuarioId == usuarioId) lista.add(b);
      } catch (_) {}
    }
    return lista..sort((a, b) => b.fecha.compareTo(a.fecha));
  }

  /// Borra el borrador y sus fotos (después de enviarse bien, o si el técnico lo descarta).
  static Future<void> borrar(String clave) async {
    final f = await _archivo(clave);
    if (await f.exists()) await f.delete();
    final fotos = await _carpeta(_seguro(clave));
    if (await fotos.exists()) await fotos.delete(recursive: true);
    cambios.value++;
  }

  /// Copia la foto o el documento elegido a la carpeta del borrador: la cámara y la galería
  /// dejan los archivos en una carpeta temporal que Android puede vaciar.
  static Future<ArchivoLocal> conservar(String clave, ArchivoLocal a) async {
    final carpeta = await _carpeta(_seguro(clave));
    if (a.ruta.startsWith(carpeta.path)) return a;
    final destino = '${carpeta.path}/${DateTime.now().microsecondsSinceEpoch}_${a.nombre.replaceAll(RegExp(r'[^\w.\-]'), '_')}';
    await File(a.ruta).copy(destino);
    return ArchivoLocal(ruta: destino, nombre: a.nombre, mime: a.mime, esImagen: a.esImagen, categoria: a.categoria, descripcion: a.descripcion);
  }

  /// Envía las hojas que se guardaron sin señal. Devuelve cuántas se enviaron.
  static Future<int> enviarPendientes() async {
    if (_enviando || usuarioId == 0) return 0;
    _enviando = true;
    var enviadas = 0;
    try {
      for (final b in (await todos()).where((b) => b.pendienteDeEnvio)) {
        try {
          await Api.i.crearHoja(Formulario.fromJson(b.envio!));
          await borrar(b.clave);
          enviadas++;
        } on ApiError catch (e) {
          if (e.status == 0) break; // sigue sin señal: se reintenta después
          // El servidor la rechazó (p. ej. la incidencia ya se cerró): vuelve a borrador con el motivo
          final f = await _archivo(b.clave);
          final rechazado = Borrador(clave: b.clave, titulo: b.titulo, usuarioId: b.usuarioId, fecha: b.fecha, datos: b.datos, error: e.message);
          await f.writeAsString(jsonEncode(rechazado.toJson()), flush: true);
          cambios.value++;
        }
      }
    } finally {
      _enviando = false;
    }
    return enviadas;
  }

  /// Cuando vuelve la conexión, se envía lo pendiente.
  static void vigilarConexion() {
    Connectivity().onConnectivityChanged.listen((estado) {
      if (!estado.contains(ConnectivityResult.none)) enviarPendientes();
    });
  }
}

/// Copia de las últimas respuestas de la API: si no hay señal, el técnico puede seguir viendo
/// sus incidencias y abrir la hoja de servicio con lo último que se descargó.
class CacheApi {
  CacheApi._();

  static Future<File> _archivo(String ruta) async {
    final base = await getApplicationSupportDirectory();
    final d = Directory('${base.path}/respuestas');
    if (!await d.exists()) await d.create(recursive: true);
    return File('${d.path}/${base64Url.encode(utf8.encode(ruta)).replaceAll('=', '')}.json');
  }

  static Future<void> guardar(String ruta, String cuerpo) async {
    try {
      await (await _archivo(ruta)).writeAsString(cuerpo);
    } catch (_) {}
  }

  static Future<String?> leer(String ruta) async {
    try {
      final f = await _archivo(ruta);
      return await f.exists() ? await f.readAsString() : null;
    } catch (_) {
      return null;
    }
  }

  /// Al cerrar sesión se borra lo descargado (son datos del técnico que salió).
  static Future<void> vaciar() async {
    final base = await getApplicationSupportDirectory();
    final d = Directory('${base.path}/respuestas');
    if (await d.exists()) await d.delete(recursive: true);
  }
}
