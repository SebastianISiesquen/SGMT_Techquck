import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/modelos.dart';
import '../core/tema.dart';
import '../widgets/ui.dart';

/// Hoja de vida del equipo: lo que le hicieron otros técnicos antes.
class EquipoPantalla extends StatefulWidget {
  final int id;
  const EquipoPantalla({super.key, required this.id});

  @override
  State<EquipoPantalla> createState() => _EquipoPantallaState();
}

class _EquipoPantallaState extends State<EquipoPantalla> {
  Equipo? _equipo;

  @override
  void initState() {
    super.initState();
    _cargar();
  }

  Future<void> _cargar() async {
    try {
      final e = await Api.i.equipo(widget.id);
      if (mounted) setState(() => _equipo = e);
    } catch (e) {
      if (mounted) mensaje(context, 'Error', '$e');
    }
  }

  @override
  Widget build(BuildContext context) {
    final equipo = _equipo;
    if (equipo == null) {
      return Scaffold(appBar: AppBar(title: const Text('Equipo')), body: const Cargando());
    }

    final eventos = equipo.eventos;
    final fallas = eventos.where((e) => e.tipo == 'falla').length;
    final ultimoMant = eventos.where((e) => e.tipo == 'mantenimiento').firstOrNull;

    return Scaffold(
      appBar: AppBar(title: Text(equipo.codigo)),
      body: RefreshIndicator(
        onRefresh: _cargar,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 48),
          children: [
            Text(
              equipo.nombre,
              style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w800, color: C.text),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                Insignia(equipo.estadoLabel, tono: colorEstado[equipo.estado]),
                equipo.garantia != null
                    ? Insignia('Garantía: ${equipo.garantia!.diasRestantes} días', tono: 'green')
                    : const Insignia('Sin garantía vigente', tono: 'red'),
              ],
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                _Dato('${eventos.length}', 'Registros'),
                const SizedBox(width: 8),
                _Dato('$fallas', 'Fallas', color: fallas > 0 ? C.danger : null),
                const SizedBox(width: 8),
                _Dato(ultimoMant != null ? fecha(ultimoMant.fecha, hora: false) : '—', 'Últ. mantenimiento'),
              ],
            ),
            const SizedBox(height: 12),
            Tarjeta(
              titulo: 'Datos del equipo',
              children: [
                Fila('Cliente', equipo.cliente),
                Fila('Ubicación', [equipo.area, equipo.ubicacion].whereType<String>().join(' · ')),
                Fila('Responsable / usuario', equipo.responsable),
                Fila('N° de serie', equipo.serie),
                Fila('Proyecto / servicio', equipo.proyectoCodigo != null ? '${equipo.proyectoCodigo} · ${equipo.proyectoNombre}' : null),
                if (equipo.garantia != null)
                  Fila('Garantía', '${equipo.garantia!.codigo} · vence ${fecha(equipo.garantia!.fechaVencimiento, hora: false)}'),
              ],
            ),
            Tarjeta(
              titulo: 'Historial (${eventos.length})',
              children: [
                if (eventos.isEmpty) const Text('Aún no hay registros.', style: TextStyle(color: C.textMuted)),
                for (final (i, e) in eventos.indexed) _Evento(e, ultimo: i == eventos.length - 1),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _Evento extends StatelessWidget {
  final EquipoEvento e;
  final bool ultimo;
  const _Evento(this.e, {required this.ultimo});

  @override
  Widget build(BuildContext context) {
    final t = tonos[e.color] ?? tonos['slate']!;
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Línea de tiempo
          SizedBox(
            width: 18,
            child: Stack(
              alignment: Alignment.topCenter,
              children: [
                if (!ultimo)
                  Positioned.fill(
                    top: 8,
                    child: Center(child: Container(width: 2, color: C.border)),
                  ),
                Container(
                  margin: const EdgeInsets.only(top: 3),
                  width: 12,
                  height: 12,
                  decoration: BoxDecoration(
                    color: t.fg,
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white, width: 2),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(bottom: 14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Wrap(
                    spacing: 6,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      Insignia(e.tipoLabel, tono: e.color),
                      Text(fecha(e.fecha), style: const TextStyle(fontSize: 12, color: C.textMuted)),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Text(
                    e.titulo,
                    style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: C.text),
                  ),
                  if (e.componente != null) Text('Componente: ${e.componente}', style: TextStyle(color: tonos['violet']!.fg, fontSize: 13)),
                  if (e.detalle != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text(e.detalle!, style: const TextStyle(fontSize: 13, color: Color(0xFF334155), height: 1.4)),
                    ),
                  const SizedBox(height: 4),
                  Text(
                    [e.usuario ?? 'Sistema', e.incidencia, e.hoja].whereType<String>().join(' · '),
                    style: const TextStyle(fontSize: 12, color: C.textMuted),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Dato extends StatelessWidget {
  final String valor, texto;
  final Color? color;
  const _Dato(this.valor, this.texto, {this.color});

  @override
  Widget build(BuildContext context) => Expanded(
    child: Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: C.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            valor,
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: color ?? C.text),
          ),
          const SizedBox(height: 2),
          Text(
            texto,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontSize: 11, color: C.textMuted),
          ),
        ],
      ),
    ),
  );
}
