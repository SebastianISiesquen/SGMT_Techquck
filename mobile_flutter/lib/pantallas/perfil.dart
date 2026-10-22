import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/modelos.dart';
import '../core/sesion.dart';
import '../core/tema.dart';
import '../widgets/ui.dart';
import 'incidencia.dart';

/// Datos del técnico, notificaciones y cierre de sesión.
class PerfilPantalla extends StatefulWidget {
  const PerfilPantalla({super.key});

  @override
  State<PerfilPantalla> createState() => _PerfilPantallaState();
}

class _PerfilPantallaState extends State<PerfilPantalla> {
  List<Notificacion> _notificaciones = [];

  @override
  void initState() {
    super.initState();
    Api.i
        .notificaciones()
        .then((n) {
          if (mounted) setState(() => _notificaciones = n);
        })
        .catchError((_) {});
  }

  Future<void> _marcarLeidas() async {
    await Api.i.leerNotificaciones();
    if (!mounted) return;
    setState(() {
      for (final n in _notificaciones) {
        n.leida = true;
      }
    });
    Sesion.i.refrescar().catchError((_) {});
  }

  @override
  Widget build(BuildContext context) {
    final u = Sesion.i.usuario;
    return Scaffold(
      appBar: AppBar(title: const Text('Mi cuenta')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 48),
        children: [
          Tarjeta(
            children: [
              Row(
                children: [
                  CircleAvatar(
                    radius: 27,
                    backgroundColor: C.brand,
                    child: Text(
                      u?.iniciales ?? '',
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 18),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          u?.nombre ?? '',
                          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: C.text),
                        ),
                        Text('${u?.rol ?? ''}${u?.cargo != null ? ' · ${u!.cargo}' : ''}', style: const TextStyle(color: C.textMuted)),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              Fila('Correo', u?.email),
              Fila('Teléfono', u?.telefono),
              Fila('Servidor', Api.i.servidor),
            ],
          ),
          Tarjeta(
            titulo: 'Notificaciones',
            accion: _notificaciones.any((n) => !n.leida)
                ? InkWell(
                    onTap: _marcarLeidas,
                    child: const Text(
                      'Marcar leídas',
                      style: TextStyle(color: C.brand, fontWeight: FontWeight.w600, fontSize: 13),
                    ),
                  )
                : null,
            children: [
              if (_notificaciones.isEmpty) const Text('Sin notificaciones.', style: TextStyle(color: C.textMuted)),
              for (final n in _notificaciones)
                InkWell(
                  onTap: n.incidenciaId == null
                      ? null
                      : () => Navigator.push(context, MaterialPageRoute(builder: (_) => IncidenciaPantalla(id: n.incidenciaId!))),
                  child: Container(
                    width: double.infinity,
                    padding: const EdgeInsets.symmetric(vertical: 10),
                    decoration: const BoxDecoration(
                      border: Border(top: BorderSide(color: C.border)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          n.titulo,
                          style: TextStyle(fontWeight: n.leida ? FontWeight.w500 : FontWeight.w800, color: C.text),
                        ),
                        Text(n.mensaje, style: const TextStyle(color: C.textMuted, fontSize: 13)),
                        Text(fecha(n.fecha), style: const TextStyle(color: C.placeholder, fontSize: 12)),
                      ],
                    ),
                  ),
                ),
            ],
          ),
          Boton('Cerrar sesión', icono: Icons.logout, variante: Variante.danger, onPressed: Sesion.i.salir),
        ],
      ),
    );
  }
}
