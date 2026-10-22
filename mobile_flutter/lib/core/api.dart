import 'dart:async';
import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';

import 'archivos.dart';
import 'modelos.dart';

class ApiError implements Exception {
  final String message;
  final int status;
  ApiError(this.message, this.status);

  @override
  String toString() => message;
}

/// Datos de un formulario multipart (campos de texto + archivos).
class Formulario {
  final Map<String, String> campos = {};
  final List<(String, ArchivoLocal)> archivos = [];

  void campo(String nombre, Object? valor) {
    if (valor != null && '$valor'.isNotEmpty) campos[nombre] = '$valor';
  }

  void archivo(String nombre, ArchivoLocal a) => archivos.add((nombre, a));
}

/// Cliente de la API REST del backend Laravel (autenticación Bearer con Sanctum).
///
/// La URL por defecto se define al compilar:
///   flutter run --dart-define=API_URL=http://10.0.2.2:8000  (emulador)
/// y el técnico puede cambiarla desde la pantalla de inicio de sesión.
class Api {
  Api._();
  static final Api i = Api._();

  static const urlPorDefecto = String.fromEnvironment('API_URL', defaultValue: 'http://10.0.2.2:8000');
  static const _kToken = 'sggp_token';
  static const _kServidor = 'sggp_servidor';

  final _almacen = const FlutterSecureStorage();
  String? _token;
  String servidor = urlPorDefecto;

  /// Se llama cuando el servidor responde 401 (token vencido o revocado).
  void Function()? alExpirar;

  Map<String, String> get authHeaders => _token == null ? {} : {'Authorization': 'Bearer $_token'};

  Future<bool> cargarSesion() async {
    _token = await _almacen.read(key: _kToken);
    servidor = await _almacen.read(key: _kServidor) ?? urlPorDefecto;
    return _token != null;
  }

  Future<void> setServidor(String url) async {
    servidor = url.trim().replaceAll(RegExp(r'/+$'), '');
    await _almacen.write(key: _kServidor, value: servidor);
  }

  Future<void> _cerrarSesionLocal() async {
    _token = null;
    await _almacen.delete(key: _kToken);
  }

  Future<dynamic> _request(String ruta, {String metodo = 'GET', Map<String, dynamic>? json, Formulario? form}) async {
    final uri = Uri.parse('$servidor/api$ruta');
    final cabeceras = {'Accept': 'application/json', ...authHeaders};
    http.Response res;

    try {
      if (form != null) {
        final req = http.MultipartRequest(metodo, uri)
          ..headers.addAll(cabeceras)
          ..fields.addAll(form.campos);
        for (final (campo, a) in form.archivos) {
          req.files.add(await http.MultipartFile.fromPath(campo, a.ruta, filename: a.nombre, contentType: MediaType.parse(a.mime)));
        }
        res = await http.Response.fromStream(await req.send()).timeout(const Duration(minutes: 2));
      } else {
        final req = http.Request(metodo, uri)..headers.addAll(cabeceras);
        if (json != null) {
          req.headers['Content-Type'] = 'application/json';
          req.body = jsonEncode(json);
        }
        res = await http.Response.fromStream(await req.send()).timeout(const Duration(seconds: 30));
      }
    } catch (_) {
      throw ApiError('No se pudo conectar con el servidor ($servidor). Verifique su conexión.', 0);
    }

    if (res.statusCode == 401) {
      await _cerrarSesionLocal();
      alExpirar?.call();
      throw ApiError('Su sesión expiró. Ingrese nuevamente.', 401);
    }
    if (res.statusCode == 204) return null;

    dynamic data;
    try {
      data = jsonDecode(utf8.decode(res.bodyBytes));
    } catch (_) {
      data = null;
    }

    if (res.statusCode >= 400) {
      // Errores de validación de Laravel: { message, errors: { campo: [msg] } }
      String? primero;
      if (data is Map && data['errors'] is Map) {
        final errores = (data['errors'] as Map).values;
        if (errores.isNotEmpty && errores.first is List && (errores.first as List).isNotEmpty) primero = '${(errores.first as List).first}';
      }
      throw ApiError(primero ?? (data is Map ? data['message']?.toString() : null) ?? 'Error ${res.statusCode}', res.statusCode);
    }
    return data;
  }

  Json _datos(dynamic r) => Json.from((r as Map)['data'] as Map);
  List<Json> _listaDatos(dynamic r) => ((r as Map)['data'] as List).map((e) => Json.from(e as Map)).toList();
  List<Json> _lista(dynamic r) => (r as List).map((e) => Json.from(e as Map)).toList();

  // ── Sesión ──────────────────────────────────────────────────────────────
  Future<Usuario> login(String email, String password) async {
    final r = await _request('/login', metodo: 'POST', json: {'email': email, 'password': password, 'dispositivo': 'app-flutter'});
    _token = r['token'] as String;
    await _almacen.write(key: _kToken, value: _token);
    return Usuario.fromJson(Json.from(r['usuario'] as Map));
  }

  Future<void> logout() async {
    try {
      await _request('/logout', metodo: 'POST');
    } finally {
      await _cerrarSesionLocal();
    }
  }

  Future<Usuario> me() async => Usuario.fromJson(Json.from((await _request('/me'))['usuario'] as Map));

  // ── Incidencias ─────────────────────────────────────────────────────────
  Future<Resumen> resumen() async => Resumen.fromJson(Json.from(await _request('/resumen') as Map));

  Future<List<Incidencia>> incidencias(String estado) async =>
      _listaDatos(await _request('/incidencias?estado=${Uri.encodeQueryComponent(estado)}')).map(Incidencia.fromJson).toList();

  Future<Incidencia> incidencia(int id) async => Incidencia.fromJson(_datos(await _request('/incidencias/$id')));

  Future<void> iniciar(int id) => _request('/incidencias/$id/iniciar', metodo: 'POST');

  Future<void> subirArchivo(int id, Formulario form) => _request('/incidencias/$id/archivos', metodo: 'POST', form: form);

  // ── Notificaciones ──────────────────────────────────────────────────────
  Future<List<Notificacion>> notificaciones() async => _lista(await _request('/notificaciones')).map(Notificacion.fromJson).toList();

  Future<void> leerNotificaciones() => _request('/notificaciones/leer', metodo: 'POST');

  // ── Hojas de servicio ───────────────────────────────────────────────────
  Future<List<ProyectoOpcion>> proyectos() async => _lista(await _request('/proyectos')).map(ProyectoOpcion.fromJson).toList();

  Future<List<HojaServicio>> hojas() async => _listaDatos(await _request('/hojas')).map(HojaServicio.fromJson).toList();

  Future<HojaServicio> hoja(int id) async => HojaServicio.fromJson(_datos(await _request('/hojas/$id')));

  Future<HojaServicio> crearHoja(Formulario form) async => HojaServicio.fromJson(_datos(await _request('/hojas', metodo: 'POST', form: form)));

  /// Acta de conformidad firmada por el cliente (foto o PDF): la hoja queda conforme.
  Future<HojaServicio> subirActa(int id, Formulario form) async =>
      HojaServicio.fromJson(_datos(await _request('/hojas/$id/acta', metodo: 'POST', form: form)));

  Future<List<String>> verificaciones() async => ((await _request('/verificaciones')) as List).map((e) => '$e').toList();

  Future<List<ArticuloAlmacen>> almacen() async => _lista(await _request('/almacen')).map(ArticuloAlmacen.fromJson).toList();

  /// { categoría normalizada: [puntos de control] }
  Future<Map<String, List<String>>> checklists() async {
    final r = await _request('/checklists');
    if (r is! Map) return {};
    return r.map((k, v) => MapEntry('$k', (v as List).map((e) => '$e').toList()));
  }

  // ── Equipos y mantenimientos ────────────────────────────────────────────
  Future<List<Equipo>> buscarEquipos(String texto) async =>
      _listaDatos(await _request('/equipos?buscar=${Uri.encodeQueryComponent(texto)}')).map(Equipo.fromJson).toList();

  Future<List<Equipo>> equiposDeProyecto(int proyectoId) async =>
      _listaDatos(await _request('/equipos?proyecto_id=$proyectoId')).map(Equipo.fromJson).toList();

  Future<Equipo> equipo(int id) async => Equipo.fromJson(_datos(await _request('/equipos/$id')));

  /// Acepta lo que lee el escáner: la URL del QR del sticker, el código EQ-… o la serie.
  Future<Equipo> equipoPorCodigo(String texto) async => Equipo.fromJson(_datos(await _request('/equipos/codigo/${Uri.encodeComponent(texto)}')));

  Future<List<Mantenimiento>> mantenimientos() async => _lista(await _request('/mantenimientos')).map(Mantenimiento.fromJson).toList();
}
