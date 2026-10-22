import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/modelos.dart';
import '../core/sesion.dart';
import '../core/tema.dart';
import '../widgets/ui.dart';
import 'equipos.dart';
import 'hojas.dart';
import 'incidencia.dart';
import 'mantenimientos.dart';
import 'perfil.dart';

const _filtros = [('abiertas', 'Pendientes'), ('resuelta', 'Resueltas'), ('cerrada', 'Cerradas')];

/// Bandeja del técnico: accesos rápidos, resumen e incidencias asignadas.
class BandejaPantalla extends StatefulWidget {
  const BandejaPantalla({super.key});

  @override
  State<BandejaPantalla> createState() => _BandejaPantallaState();
}

class _BandejaPantallaState extends State<BandejaPantalla> {
  String _filtro = 'abiertas';
  List<Incidencia> _items = [];
  Resumen? _resumen;
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
      final r = await Future.wait([Api.i.incidencias(_filtro), Api.i.resumen()]);
      if (!mounted) return;
      setState(() {
        _items = r[0] as List<Incidencia>;
        _resumen = r[1] as Resumen;
      });
    } catch (e) {
      if (mounted) setState(() => _error = '$e');
    } finally {
      if (mounted) setState(() => _cargando = false);
    }
  }

  /// Abre una pantalla y recarga la bandeja al volver (p. ej. después de registrar una visita).
  Future<void> _abrir(Widget pantalla) async {
    await Navigator.push(context, MaterialPageRoute(builder: (_) => pantalla));
    if (mounted) _cargar();
  }

  @override
  Widget build(BuildContext context) {
    final usuario = Sesion.i.usuario;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Mis incidencias'),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: InkWell(
              customBorder: const CircleBorder(),
              onTap: () => _abrir(const PerfilPantalla()),
              child: Badge(
                isLabelVisible: (usuario?.noLeidas ?? 0) > 0,
                smallSize: 10,
                child: CircleAvatar(
                  radius: 17,
                  backgroundColor: C.brand,
                  child: Text(
                    usuario?.iniciales ?? '',
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _cargar,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 40),
          children: [
            Text(
              'Hola, ${usuario?.primerNombre ?? ''}',
              style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: C.text),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                _Acceso(Icons.qr_code_scanner, 'Equipos', 'Escanear o buscar', () => _abrir(const EquiposPantalla())),
                const SizedBox(width: 10),
                _Acceso(Icons.event_available, 'Mantenimientos', 'Programados', () => _abrir(const MantenimientosPantalla())),
                const SizedBox(width: 10),
                _Acceso(Icons.assignment_outlined, 'Hojas', 'De servicio', () => _abrir(const HojasPantalla())),
              ],
            ),
            const SizedBox(height: 12),
            if (_resumen != null)
              Row(
                children: [
                  _Kpi(_resumen!.asignadas, 'Asignadas', C.brand),
                  const SizedBox(width: 10),
                  _Kpi(_resumen!.enProceso, 'En proceso', const Color(0xFFB45309)),
                  const SizedBox(width: 10),
                  _Kpi(_resumen!.criticas, 'Alta / crítica', C.danger),
                ],
              ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 8,
              children: [
                for (final (valor, texto) in _filtros)
                  Opcion(
                    texto,
                    activo: _filtro == valor,
                    onTap: () {
                      setState(() => _filtro = valor);
                      _cargar();
                    },
                  ),
              ],
            ),
            const SizedBox(height: 14),
            if (_error.isNotEmpty) TextoError(_error),
            if (_cargando && _items.isEmpty) const Padding(padding: EdgeInsets.all(30), child: Cargando()),
            if (!_cargando && _items.isEmpty && _error.isEmpty) const Vacio('No tiene incidencias en esta bandeja.'),
            for (final inc in _items) _ItemIncidencia(inc, onTap: () => _abrir(IncidenciaPantalla(id: inc.id))),
          ],
        ),
      ),
    );
  }
}

class _ItemIncidencia extends StatelessWidget {
  final Incidencia inc;
  final VoidCallback onTap;
  const _ItemIncidencia(this.inc, {required this.onTap});

  @override
  Widget build(BuildContext context) {
    return ItemLista(
      onTap: onTap,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(inc.codigo, style: const TextStyle(fontSize: 12, color: C.textMuted)),
            ),
            Insignia(inc.prioridadLabel, tono: colorPrioridad[inc.prioridad]),
          ],
        ),
        const SizedBox(height: 6),
        Text(
          inc.titulo,
          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: C.text),
        ),
        const SizedBox(height: 2),
        Meta(Icons.account_balance_outlined, inc.proyecto.entidad),
        if (inc.ubicacionDetalle != null) Meta(Icons.place_outlined, inc.ubicacionDetalle!),
        if (inc.proximaVisita != null)
          Meta(Icons.event, 'Próxima visita: ${fecha(inc.proximaVisita, hora: false)}', color: const Color(0xFF1D4ED8), negrita: true),
        const SizedBox(height: 10),
        Row(
          children: [
            Insignia(inc.estadoLabel, tono: colorEstado[inc.estado]),
            const Spacer(),
            Text(
              inc.slaVencido ? 'Fuera de plazo' : fecha(inc.fechaReporte),
              style: TextStyle(
                fontSize: 13,
                color: inc.slaVencido ? C.danger : C.textMuted,
                fontWeight: inc.slaVencido ? FontWeight.w700 : FontWeight.normal,
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _Acceso extends StatelessWidget {
  final IconData icono;
  final String texto, detalle;
  final VoidCallback onTap;
  const _Acceso(this.icono, this.texto, this.detalle, this.onTap);

  @override
  Widget build(BuildContext context) => Expanded(
    child: Material(
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: const BorderSide(color: C.border),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icono, color: C.brand, size: 26),
              const SizedBox(height: 6),
              FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: Alignment.centerLeft,
                    child: Text(texto, style: const TextStyle(fontWeight: FontWeight.w700, color: C.text)),
                  ),
              Text(
                detalle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 11, color: C.textMuted),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

class _Kpi extends StatelessWidget {
  final int valor;
  final String texto;
  final Color tono;
  const _Kpi(this.valor, this.texto, this.tono);

  @override
  Widget build(BuildContext context) => Expanded(
    child: Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: C.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '$valor',
            style: TextStyle(fontSize: 26, fontWeight: FontWeight.w800, color: tono),
          ),
          Text(
            texto,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontSize: 12, color: C.textMuted),
          ),
        ],
      ),
    ),
  );
}
