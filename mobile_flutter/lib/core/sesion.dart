import 'package:flutter/material.dart';

import 'api.dart';
import 'borradores.dart';
import 'modelos.dart';

final navegador = GlobalKey<NavigatorState>();

/// Estado de la sesión del técnico: la app muestra el login o la bandeja según [usuario].
class Sesion extends ChangeNotifier {
  Sesion._();
  static final Sesion i = Sesion._();

  Usuario? usuario;
  bool cargando = true;

  Future<void> iniciar() async {
    Api.i.alExpirar = _alSalir;
    Borradores.vigilarConexion();
    try {
      if (await Api.i.cargarSesion()) usuario = await Api.i.me();
    } catch (_) {
      usuario = null;
    }
    _alEntrar();
    cargando = false;
    notifyListeners();
  }

  Future<void> ingresar(String email, String password) async {
    usuario = await Api.i.login(email, password);
    _alEntrar();
    notifyListeners();
  }

  /// Los borradores son de cada técnico; al entrar se envían las hojas que quedaron sin señal.
  void _alEntrar() {
    Borradores.usuarioId = usuario?.id ?? 0;
    if (usuario != null) Borradores.enviarPendientes();
  }

  Future<void> salir() async {
    await Api.i.logout().catchError((_) {});
    _alSalir();
  }

  Future<void> refrescar() async {
    usuario = await Api.i.me();
    notifyListeners();
  }

  /// Vuelve al login cerrando las pantallas abiertas.
  void _alSalir() {
    usuario = null;
    Borradores.usuarioId = 0;
    CacheApi.vaciar();
    navegador.currentState?.popUntil((r) => r.isFirst);
    notifyListeners();
  }
}
