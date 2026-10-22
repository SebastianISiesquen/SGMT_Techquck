import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api.dart';
import '../core/archivos.dart';
import '../core/modelos.dart';
import '../core/tema.dart';
import '../widgets/ui.dart';
import 'equipo.dart';
import 'hoja.dart';
import 'nueva_hoja.dart';

/// Detalle de la incidencia: datos, equipo, garantía, atenciones, actas e historial.
class IncidenciaPantalla extends StatefulWidget {
  final int id;
  const IncidenciaPantalla({super.key, required this.id});

  @override
  State<IncidenciaPantalla> createState() => _IncidenciaPantallaState();
}

class _IncidenciaPantallaState extends State<IncidenciaPantalla> {
  Incidencia? _inc;
  String _accion = '';

  @override
  void initState() {
    super.initState();
    _cargar();
  }

  Future<void> _cargar() async {
    try {
      final inc = await Api.i.incidencia(widget.id);
      if (mounted) setState(() => _inc = inc);
    } catch (e) {
      if (mounted) mensaje(context, 'Error', '$e');
    }
  }

  Future<void> _abrir(Widget pantalla) async {
    await Navigator.push(context, MaterialPageRoute(builder: (_) => pantalla));
    if (mounted) _cargar();
  }

  Future<void> _iniciar() async {
    setState(() => _accion = 'iniciar');
    try {
      await Api.i.iniciar(widget.id);
      await _cargar();
    } catch (e) {
      if (mounted) mensaje(context, 'Error', '$e');
    } finally {
      if (mounted) setState(() => _accion = '');
    }
  }

  /// Subida suelta: p. ej. el acta firmada que la entidad entrega después.
  Future<void> _subir(Future<List<ArchivoLocal>> Function() fuente, String categoria) async {
    final archivos = await fuente();
    if (archivos.isEmpty) return;
    setState(() => _accion = 'subir');
    try {
      for (final a in archivos) {
        final form = Formulario()
          ..archivo('archivo', a)
          ..campo('categoria', a.esImagen && categoria != 'acta_conformidad' ? 'foto' : categoria);
        await Api.i.subirArchivo(widget.id, form);
      }
      await _cargar();
      if (mounted) mensaje(context, 'Listo', 'Archivo(s) subido(s) correctamente.');
    } catch (e) {
      if (mounted) mensaje(context, 'Error al subir', '$e');
    } finally {
      if (mounted) setState(() => _accion = '');
    }
  }

  @override
  Widget build(BuildContext context) {
    final inc = _inc;
    if (inc == null) {
      return Scaffold(appBar: AppBar(title: const Text('Incidencia')), body: const Cargando());
    }

    final puedeAdjuntar = ['asignada', 'en_proceso', 'resuelta'].contains(inc.estado);
    final evidencias = [...inc.archivos, for (final a in inc.atenciones) ...a.archivos];
    final tieneActa = evidencias.any((a) => a.categoria == 'acta_conformidad');
    final tieneHojaFirmada = inc.hojas.any((h) => h.estado == 'conforme');
    final hojasSinActa = inc.hojas.where((h) => h.estado == 'registrada').toList();
    final nuevaHoja = NuevaHojaPantalla(incidenciaId: inc.id);

    return Scaffold(
      appBar: AppBar(title: Text(inc.codigo)),
      body: RefreshIndicator(
        onRefresh: _cargar,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 48),
          children: [
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                Insignia(inc.estadoLabel, tono: colorEstado[inc.estado]),
                Insignia('Prioridad ${inc.prioridadLabel}', tono: colorPrioridad[inc.prioridad]),
                if (inc.slaVencido) const Insignia('Fuera de plazo', tono: 'red'),
                Insignia(
                  inc.coberturaLabel,
                  tono: inc.cobertura == 'garantia'
                      ? 'green'
                      : inc.cobertura == 'contrato'
                      ? 'blue'
                      : 'amber',
                ),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              inc.titulo,
              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: C.text),
            ),
            const SizedBox(height: 6),
            Text(inc.descripcion, style: const TextStyle(fontSize: 15, color: Color(0xFF334155), height: 1.4)),
            const SizedBox(height: 16),

            if (inc.proximaVisita != null && ['asignada', 'en_proceso'].contains(inc.estado))
              Aviso(
                tono: 'blue',
                children: [
                  Text('Próxima visita: ${fecha(inc.proximaVisita, hora: false)}', style: const TextStyle(fontWeight: FontWeight.w800)),
                  if (inc.motivoReprogramacion != null) Text('Pendiente: ${inc.motivoReprogramacion}'),
                ],
              ),

            if (hojasSinActa.isNotEmpty)
              Aviso(
                children: [
                  const Text('Falta subir el acta firmada:', style: TextStyle(fontWeight: FontWeight.w700)),
                  for (final h in hojasSinActa)
                    TextButton.icon(
                      style: TextButton.styleFrom(padding: EdgeInsets.zero, alignment: Alignment.centerLeft),
                      onPressed: () => _abrir(HojaPantalla(id: h.id)),
                      icon: const Icon(Icons.description_outlined, size: 18),
                      label: Text('${h.numero}  ›', style: const TextStyle(fontWeight: FontWeight.w700)),
                    ),
                ],
              ),

            // Acciones del técnico
            if (inc.estado == 'asignada' && inc.puedeAtender) ...[
              Boton('Iniciar atención', icono: Icons.play_arrow, variante: Variante.secondary, cargando: _accion == 'iniciar', onPressed: _iniciar),
              const SizedBox(height: 10),
            ],
            if (inc.puedeAtender) ...[
              Boton('Registrar visita (hoja de servicio)', icono: Icons.assignment_outlined, onPressed: () => _abrir(nuevaHoja)),
              const SizedBox(height: 10),
            ],
            if (puedeAdjuntar) ...[
              Row(
                children: [
                  Expanded(
                    child: Boton(
                      tieneActa ? 'Otra acta' : 'Subir acta',
                      icono: Icons.upload_file,
                      variante: tieneActa ? Variante.secondary : Variante.success,
                      cargando: _accion == 'subir',
                      onPressed: () => _subir(elegirDocumento, 'acta_conformidad'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Boton(
                      'Foto',
                      icono: Icons.photo_camera_outlined,
                      variante: Variante.secondary,
                      onPressed: _accion == 'subir' ? null : () => _subir(tomarFoto, 'foto'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
            ],
            if (inc.estado == 'resuelta' && !tieneHojaFirmada) ...[
              Boton(
                'Hoja de servicio para el acta',
                icono: Icons.assignment_outlined,
                variante: Variante.secondary,
                onPressed: () => _abrir(nuevaHoja),
              ),
              const SizedBox(height: 10),
            ],
            if (inc.estado == 'resuelta' && !tieneActa && !tieneHojaFirmada)
              const Aviso(children: [Text('Para cerrar, el supervisor necesita el acta de conformidad firmada por el cliente.')]),
            const SizedBox(height: 6),

            if (inc.equipo != null) _tarjetaEquipo(inc.equipo!),

            Tarjeta(
              titulo: 'Lugar y contacto',
              children: [
                Fila('Cliente', inc.proyecto.entidad),
                if (inc.proyecto.contratante != null) Fila('Contratado por', inc.proyecto.contratante),
                Fila('Proyecto', '${inc.proyecto.codigo} · ${inc.proyecto.nombre}'),
                Fila('Ubicación / equipo', inc.ubicacionDetalle ?? inc.proyecto.ubicacion),
                Fila('Reportado por', inc.reportadoPor ?? inc.proyecto.contacto),
                if (inc.proyecto.contactoTelefono != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: Boton(
                      'Llamar ${inc.proyecto.contactoTelefono}',
                      icono: Icons.phone,
                      variante: Variante.secondary,
                      onPressed: () => launchUrl(Uri(scheme: 'tel', path: inc.proyecto.contactoTelefono)),
                    ),
                  ),
                Fila('Fecha de reporte', fecha(inc.fechaReporte)),
                Fila('Fecha límite', fecha(inc.fechaLimite)),
              ],
            ),

            if (inc.garantia != null)
              Tarjeta(
                titulo: 'Garantía',
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          inc.garantia!.tipo,
                          style: const TextStyle(fontWeight: FontWeight.w700, color: C.text),
                        ),
                      ),
                      Insignia(inc.garantia!.estadoLabel, tono: colorEstado[inc.garantia!.estado]),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Fila('Vence', fecha(inc.garantia!.fechaVencimiento, hora: false)),
                  Fila('Cobertura', inc.garantia!.cobertura),
                  Fila('Condiciones', inc.garantia!.condiciones),
                ],
              ),

            Tarjeta(
              titulo: 'Atenciones (${inc.atenciones.length})',
              children: [
                if (inc.atenciones.isEmpty) const Text('Aún no se registran atenciones.', style: TextStyle(color: C.textMuted)),
                for (final a in inc.atenciones)
                  Container(
                    padding: const EdgeInsets.only(top: 12),
                    margin: const EdgeInsets.only(top: 4),
                    decoration: const BoxDecoration(
                      border: Border(top: BorderSide(color: C.border)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text('${fecha(a.fechaInicio)} · ${a.tecnico ?? ''}', style: const TextStyle(color: C.textMuted, fontSize: 12)),
                            ),
                            Insignia(a.resultadoLabel, tono: colorEstado[a.resultado]),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Fila('Diagnóstico', a.diagnostico),
                        Fila('Actividades', a.actividades),
                        if (a.observaciones != null) Fila('Observaciones', a.observaciones),
                        Galeria(a.archivos),
                      ],
                    ),
                  ),
              ],
            ),

            if (inc.archivos.isNotEmpty) Tarjeta(titulo: 'Documentos de la incidencia', children: [Galeria(inc.archivos)]),

            if (inc.hojas.isNotEmpty)
              Tarjeta(
                titulo: 'Hojas de servicio',
                children: [
                  for (final h in inc.hojas)
                    InkWell(
                      onTap: () => _abrir(HojaPantalla(id: h.id)),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(vertical: 8),
                        child: Row(
                          children: [
                            Expanded(
                              child: Text(
                                '${h.numero}  ›',
                                style: const TextStyle(fontWeight: FontWeight.w600, color: C.text),
                              ),
                            ),
                            Insignia(h.estadoLabel, tono: h.estado == 'registrada' ? 'amber' : colorEstado[h.estado]),
                          ],
                        ),
                      ),
                    ),
                ],
              ),

            Tarjeta(
              titulo: 'Historial',
              children: [
                for (final h in inc.historial)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          width: 10,
                          height: 10,
                          margin: const EdgeInsets.only(top: 5, right: 10),
                          decoration: const BoxDecoration(color: C.brand, shape: BoxShape.circle),
                        ),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                '${h.accion}${h.estadoNuevo != null ? ' → ${h.estadoNuevo}' : ''}',
                                style: const TextStyle(fontWeight: FontWeight.w600, color: C.text),
                              ),
                              if (h.detalle != null) Text(h.detalle!, style: const TextStyle(color: C.textMuted, fontSize: 13)),
                              Text('${fecha(h.fecha)} · ${h.usuario ?? 'Sistema'}', style: const TextStyle(color: C.placeholder, fontSize: 12)),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _tarjetaEquipo(EquipoIncidencia e) {
    void verHistorial() => _abrir(EquipoPantalla(id: e.id));
    return Tarjeta(
      titulo: 'Equipo afectado',
      children: [
        InkWell(
          onTap: verHistorial,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${e.codigo} · ${e.nombre}',
                style: const TextStyle(fontWeight: FontWeight.w700, color: C.text),
              ),
              Text([e.ubicacion, e.responsable].whereType<String>().join(' · '), style: const TextStyle(color: C.textMuted, fontSize: 13)),
            ],
          ),
        ),
        const SizedBox(height: 10),
        if (e.antecedentes.isEmpty)
          const Text('Sin antecedentes registrados.', style: TextStyle(color: C.textMuted, fontSize: 13))
        else
          Aviso(
            children: [
              const Text('Antecedentes del equipo', style: TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(height: 6),
              for (final a in e.antecedentes)
                Padding(
                  padding: const EdgeInsets.only(bottom: 4),
                  child: Text.rich(
                    TextSpan(
                      style: const TextStyle(fontSize: 13),
                      children: [
                        TextSpan(
                          text: '${fecha(a.fecha, hora: false)} · ${a.tipoLabel}: ',
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        TextSpan(text: a.titulo),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        Boton('Ver historial completo', variante: Variante.secondary, icono: Icons.history, onPressed: verHistorial),
      ],
    );
  }
}
