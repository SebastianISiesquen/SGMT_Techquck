import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/modelos.dart';
import '../core/tema.dart';
import '../widgets/ui.dart';
import 'nueva_hoja.dart';

/// Visitas de mantenimiento preventivo asignadas al técnico (próximos 60 días y atrasadas).
class MantenimientosPantalla extends StatefulWidget {
  const MantenimientosPantalla({super.key});

  @override
  State<MantenimientosPantalla> createState() => _MantenimientosPantallaState();
}

class _MantenimientosPantallaState extends State<MantenimientosPantalla> {
  List<Mantenimiento> _items = [];
  bool _cargando = false;
  String _error = '';

  @override
  void initState() {
    super.initState();
    _cargar();
  }

  Future<void> _cargar() async {
    setState(() {
      _cargando = true;
      _error = '';
    });
    try {
      final r = await Api.i.mantenimientos();
      if (mounted) setState(() => _items = r);
    } catch (e) {
      if (mounted) setState(() => _error = '$e');
    } finally {
      if (mounted) setState(() => _cargando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Mis mantenimientos')),
      body: RefreshIndicator(
        onRefresh: _cargar,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 40),
          children: [
            if (_error.isNotEmpty) TextoError(_error),
            if (_cargando && _items.isEmpty) const Padding(padding: EdgeInsets.all(30), child: Cargando()),
            if (!_cargando && _items.isEmpty && _error.isEmpty) const Vacio('No tiene mantenimientos programados en los próximos 60 días.'),
            for (final m in _items)
              ItemLista(
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          fecha(m.fechaProgramada, hora: false),
                          style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: C.text),
                        ),
                      ),
                      Insignia(
                        m.atrasado
                            ? 'Atrasado'
                            : m.diasRestantes == 0
                            ? 'Hoy'
                            : 'En ${m.diasRestantes} días',
                        tono: m.atrasado
                            ? 'red'
                            : m.diasRestantes <= 7
                            ? 'amber'
                            : 'blue',
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    m.etiqueta,
                    style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: C.text),
                  ),
                  Text(m.plan, style: const TextStyle(fontSize: 13, color: C.textMuted)),
                  Meta(Icons.business_outlined, m.cliente),
                  if (m.alcance != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: Text(
                        m.alcance!,
                        maxLines: 3,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontSize: 13, color: C.textMuted),
                      ),
                    ),
                  const SizedBox(height: 12),
                  Boton(
                    'Registrar hoja de servicio',
                    icono: Icons.assignment_outlined,
                    onPressed: () async {
                      await Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => NuevaHojaPantalla(mantenimientoId: m.id, proyectoId: m.proyectoId, etiqueta: m.etiqueta),
                        ),
                      );
                      if (mounted) _cargar();
                    },
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}
