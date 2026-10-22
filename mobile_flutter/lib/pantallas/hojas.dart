import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/modelos.dart';
import '../core/tema.dart';
import '../widgets/ui.dart';
import 'hoja.dart';
import 'nueva_hoja.dart';

/// Hojas de servicio registradas por el técnico.
class HojasPantalla extends StatefulWidget {
  const HojasPantalla({super.key});

  @override
  State<HojasPantalla> createState() => _HojasPantallaState();
}

class _HojasPantallaState extends State<HojasPantalla> {
  List<HojaServicio> _hojas = [];
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
      final h = await Api.i.hojas();
      if (mounted) setState(() => _hojas = h);
    } catch (e) {
      if (mounted) setState(() => _error = '$e');
    } finally {
      if (mounted) setState(() => _cargando = false);
    }
  }

  Future<void> _abrir(Widget pantalla) async {
    await Navigator.push(context, MaterialPageRoute(builder: (_) => pantalla));
    if (mounted) _cargar();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Hojas de servicio')),
      body: RefreshIndicator(
        onRefresh: _cargar,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 40),
          children: [
            Boton('Nueva hoja (preventivo / inspección)', icono: Icons.add, onPressed: () => _abrir(const NuevaHojaPantalla())),
            const Padding(
              padding: EdgeInsets.only(top: 8, bottom: 14),
              child: Text(
                'Para un servicio correctivo, cree la hoja desde la incidencia.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 12, color: C.textMuted),
              ),
            ),
            if (_error.isNotEmpty) TextoError(_error),
            if (_cargando && _hojas.isEmpty) const Padding(padding: EdgeInsets.all(30), child: Cargando()),
            if (!_cargando && _hojas.isEmpty && _error.isEmpty) const Vacio('Aún no registra hojas de servicio.'),
            for (final h in _hojas)
              ItemLista(
                onTap: () => _abrir(HojaPantalla(id: h.id)),
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          h.numero,
                          style: const TextStyle(fontSize: 12, color: C.textMuted, fontWeight: FontWeight.w600),
                        ),
                      ),
                      Insignia(h.estadoLabel, tono: h.estado == 'registrada' ? 'amber' : colorEstado[h.estado]),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Text(
                    h.tipoServicioLabel,
                    style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: C.text),
                  ),
                  Meta(Icons.account_balance_outlined, h.proyectoEntidad),
                  Meta(Icons.folder_outlined, h.proyectoNombre),
                  Meta(
                    Icons.calendar_today_outlined,
                    '${fecha(h.fecha, hora: false)} · ${h.horaLlegada}${h.horaSalida != null ? '–${h.horaSalida}' : ''}',
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}
