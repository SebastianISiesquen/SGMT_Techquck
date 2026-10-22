import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api.dart';
import '../core/archivos.dart';
import '../core/modelos.dart';
import '../core/tema.dart';
import '../widgets/ui.dart';
import 'incidencia.dart';

/// Detalle de la hoja: PDF para imprimir y subida del acta firmada por el cliente.
class HojaPantalla extends StatefulWidget {
  final int id;
  const HojaPantalla({super.key, required this.id});

  @override
  State<HojaPantalla> createState() => _HojaPantallaState();
}

class _HojaPantallaState extends State<HojaPantalla> {
  HojaServicio? _hoja;
  bool _subiendo = false;

  @override
  void initState() {
    super.initState();
    _cargar();
  }

  Future<void> _cargar() async {
    try {
      final h = await Api.i.hoja(widget.id);
      if (mounted) setState(() => _hoja = h);
    } catch (e) {
      if (mounted) mensaje(context, 'Error', '$e');
    }
  }

  Future<void> _subirActa(Future<List<ArchivoLocal>> Function() fuente) async {
    final archivos = await fuente();
    if (archivos.isEmpty) return;
    setState(() => _subiendo = true);
    try {
      final form = Formulario();
      for (var i = 0; i < archivos.length; i++) {
        form.archivo('actas[$i]', archivos[i]);
      }
      final h = await Api.i.subirActa(widget.id, form);
      if (!mounted) return;
      setState(() => _hoja = h);
      mensaje(context, 'Acta registrada', 'La hoja quedó con el acta de conformidad del cliente.');
    } catch (e) {
      if (mounted) mensaje(context, 'No se pudo subir', '$e');
    } finally {
      if (mounted) setState(() => _subiendo = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final hoja = _hoja;
    if (hoja == null) {
      return Scaffold(appBar: AppBar(title: const Text('Hoja de servicio')), body: const Cargando());
    }

    final actas = hoja.archivos.where((a) => a.categoria == 'acta_conformidad').toList();
    final fotos = hoja.archivos.where((a) => a.esImagen && a.categoria != 'acta_conformidad').toList();

    return Scaffold(
      appBar: AppBar(title: Text(hoja.numero)),
      body: RefreshIndicator(
        onRefresh: _cargar,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 48),
          children: [
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                Insignia(hoja.estadoLabel, tono: hoja.estado == 'registrada' ? 'amber' : colorEstado[hoja.estado]),
                if (hoja.resultadoLabel != null) Insignia(hoja.resultadoLabel!, tono: colorEstado[hoja.resultado] ?? 'slate'),
              ],
            ),
            const SizedBox(height: 12),

            if (hoja.proximaVisita != null)
              Aviso(
                tono: 'blue',
                children: [
                  Text('Continúa el ${fecha(hoja.proximaVisita, hora: false)}', style: const TextStyle(fontWeight: FontWeight.w700)),
                  if (hoja.pendientes != null) Text('Pendiente: ${hoja.pendientes}'),
                ],
              ),

            // Siguiente paso: el acta firmada
            if (!hoja.tieneActa && hoja.estado != 'anulada')
              Aviso(
                children: [
                  const Text('Falta el acta de conformidad', style: TextStyle(fontWeight: FontWeight.w800)),
                  const SizedBox(height: 4),
                  const Text(
                    'Imprima o muestre la hoja, que el cliente la firme y selle, y suba una foto o el PDF del acta firmada.',
                    style: TextStyle(fontSize: 13),
                  ),
                  if (hoja.puedeSubirActa) ...[
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        Expanded(
                          child: Boton(
                            'Foto del acta',
                            icono: Icons.photo_camera_outlined,
                            variante: Variante.success,
                            cargando: _subiendo,
                            onPressed: () => _subirActa(tomarFoto),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Boton(
                            'Archivo / PDF',
                            icono: Icons.attach_file,
                            variante: Variante.secondary,
                            onPressed: _subiendo ? null : () => _subirActa(elegirDocumento),
                          ),
                        ),
                      ],
                    ),
                  ],
                ],
              ),

            Boton(
              'Ver / compartir hoja en PDF',
              icono: Icons.picture_as_pdf_outlined,
              variante: Variante.secondary,
              onPressed: () => launchUrl(Uri.parse(hoja.pdfUrl), mode: LaunchMode.externalApplication),
            ),
            const SizedBox(height: 12),

            Tarjeta(
              titulo: 'Servicio',
              children: [
                Fila('Tipo', hoja.tipoServicioLabel),
                Fila('Cliente', hoja.proyectoEntidad),
                Fila('Proyecto / servicio', hoja.proyectoNombre),
                if (hoja.incidenciaId != null)
                  Fila(
                    'Incidencia',
                    '${hoja.incidenciaCodigo}  ›',
                    onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => IncidenciaPantalla(id: hoja.incidenciaId!))),
                  ),
                if (hoja.mantenimiento != null) Fila('Mantenimiento', hoja.mantenimiento),
                Fila(
                  'Fecha y horario',
                  '${fecha(hoja.fecha, hora: false)} · ${hoja.horaLlegada}${hoja.horaSalida != null ? ' – ${hoja.horaSalida}' : ''}',
                ),
                if (hoja.diagnostico != null) Fila('Diagnóstico', hoja.diagnostico),
                Fila('Trabajo realizado', hoja.trabajoRealizado),
                if (hoja.observaciones != null) Fila('Observaciones', hoja.observaciones),
                if (hoja.recomendaciones != null) Fila('Recomendaciones', hoja.recomendaciones),
                if (hoja.receptorNombre != null)
                  Fila('Recibe el servicio', [hoja.receptorNombre, hoja.receptorCargo].whereType<String>().join(' · ')),
              ],
            ),

            if (hoja.mediciones.isNotEmpty)
              Tarjeta(
                titulo: 'Mediciones',
                children: [
                  for (final m in hoja.mediciones)
                    Container(
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      decoration: const BoxDecoration(
                        border: Border(bottom: BorderSide(color: C.border)),
                      ),
                      child: Row(
                        children: [
                          Expanded(
                            child: Text(m.parametro, style: const TextStyle(color: C.textMuted)),
                          ),
                          Text(
                            '${m.valor} ${m.unidad ?? ''}',
                            style: const TextStyle(fontWeight: FontWeight.w700, color: C.text),
                          ),
                        ],
                      ),
                    ),
                ],
              ),

            if (hoja.equipos.isNotEmpty)
              Tarjeta(
                titulo: 'Equipos (${hoja.equipos.length})',
                children: [
                  for (final (i, e) in hoja.equipos.indexed)
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      decoration: BoxDecoration(
                        border: i > 0 ? const Border(top: BorderSide(color: C.border)) : null,
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            e.equipo,
                            style: const TextStyle(fontWeight: FontWeight.w700, color: C.text),
                          ),
                          Text(
                            [e.marca, e.modelo, if (e.serie != null) 'S/N ${e.serie}'].whereType<String>().join(' · '),
                            style: const TextStyle(color: C.textMuted, fontSize: 13),
                          ),
                          Text('${e.accion} → ${e.estadoFinal}', style: const TextStyle(color: C.textMuted, fontSize: 13)),
                        ],
                      ),
                    ),
                ],
              ),

            if (fotos.isNotEmpty) Tarjeta(titulo: 'Fotos (${fotos.length})', children: [Galeria(fotos, lado: 100)]),

            if (actas.isNotEmpty)
              Tarjeta(
                titulo: 'Acta de conformidad',
                children: [
                  for (final a in actas)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 4),
                      child: Text(
                        '${a.nombre} · ${fecha(a.fecha)}',
                        style: const TextStyle(color: Color(0xFF065F46), fontWeight: FontWeight.w600),
                      ),
                    ),
                  if (hoja.puedeSubirActa) ...[
                    const SizedBox(height: 8),
                    Boton(
                      'Otra página del acta',
                      icono: Icons.add,
                      variante: Variante.secondary,
                      cargando: _subiendo,
                      onPressed: () => _subirActa(tomarFoto),
                    ),
                  ],
                ],
              ),
          ],
        ),
      ),
    );
  }
}
