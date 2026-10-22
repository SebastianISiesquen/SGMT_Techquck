import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'core/sesion.dart';
import 'core/tema.dart';
import 'pantallas/bandeja.dart';
import 'pantallas/login.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);
  // Íconos claros en la barra de estado: todas las pantallas tienen la cabecera azul oscuro
  SystemChrome.setSystemUIOverlayStyle(SystemUiOverlayStyle.light);
  Sesion.i.iniciar();
  runApp(const SggpTecnico());
}

class SggpTecnico extends StatelessWidget {
  const SggpTecnico({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'SGGP Técnico',
      debugShowCheckedModeBanner: false,
      navigatorKey: navegador,
      theme: temaApp(),
      // Sin sesión → inicio de sesión del técnico; con sesión → su bandeja de incidencias.
      home: ListenableBuilder(
        listenable: Sesion.i,
        builder: (context, _) {
          if (Sesion.i.cargando) return const _Arranque();
          return Sesion.i.usuario == null ? const LoginPantalla() : const BandejaPantalla();
        },
      ),
    );
  }
}

class _Arranque extends StatelessWidget {
  const _Arranque();

  @override
  Widget build(BuildContext context) => const Scaffold(
    backgroundColor: C.brandDark,
    body: Center(child: CircularProgressIndicator(color: Colors.white)),
  );
}
