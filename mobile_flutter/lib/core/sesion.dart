import 'package:flutter/material.dart';

import 'api.dart';
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
    try {
      if (await Api.i.cargarSesion()) usuario = await Api.i.me();
    } catch (_) {
      usuario = null;
    }
    cargando = false;
    notifyListeners();
  }

  Future<void> ingresar(String email, String password) async {
    usuario = await Api.i.login(email, password);
    notifyListeners();
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
    navegador.currentState?.popUntil((r) => r.isFirst);
    notifyListeners();
  }
}
