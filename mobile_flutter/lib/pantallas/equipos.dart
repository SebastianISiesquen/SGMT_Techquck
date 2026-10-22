import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/modelos.dart';
import '../core/tema.dart';
import '../widgets/ui.dart';
import 'equipo.dart';
import 'escanear.dart';

/// Buscar un equipo por serie, código del sticker, marca o modelo; o escanear su QR.
class EquiposPantalla extends StatefulWidget {
  const EquiposPantalla({super.key});

  @override
  State<EquiposPantalla> createState() => _EquiposPantallaState();
}

class _EquiposPantallaState extends State<EquiposPantalla> {
  final _texto = TextEditingController();
  List<Equipo>? _items;
  bool _cargando = false;
  String _error = '';

  @override
  void dispose() {
    _texto.dispose();
    super.dispose();
  }

  Future<void> _buscar() async {
    final t = _texto.text.trim();
    if (t.length < 2) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _cargando = true;
      _error = '';
    });
    try {
      final r = await Api.i.buscarEquipos(t);
      if (mounted) setState(() => _items = r);
    } catch (e) {
      if (mounted) setState(() => _error = '$e');
    } finally {
      if (mounted) setState(() => _cargando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final items = _items;
    return Scaffold(
      appBar: AppBar(title: const Text('Equipos')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 40),
        children: [
          Boton(
            'Escanear QR del equipo',
            icono: Icons.qr_code_scanner,
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const EscanearPantalla())),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _texto,
                  textCapitalization: TextCapitalization.characters,
                  textInputAction: TextInputAction.search,
                  onSubmitted: (_) => _buscar(),
                  decoration: const InputDecoration(hintText: 'Serie, código EQ-…, marca o modelo'),
                ),
              ),
              const SizedBox(width: 8),
              SizedBox(
                height: 46,
                child: FilledButton(
                  onPressed: _buscar,
                  style: FilledButton.styleFrom(
                    backgroundColor: C.brandDark,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: const Text('Buscar', style: TextStyle(fontWeight: FontWeight.w700)),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (_cargando) const Padding(padding: EdgeInsets.all(16), child: Cargando()),
          if (_error.isNotEmpty) TextoError(_error),
          if (items != null && items.isEmpty && !_cargando) const Vacio('No se encontraron equipos.'),
          if (items == null)
            const Vacio('Conozca el historial de un equipo antes de atenderlo: mantenimientos, fallas, diagnósticos y cambios de componentes.'),
          for (final e in items ?? <Equipo>[])
            ItemLista(
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => EquipoPantalla(id: e.id))),
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        e.codigo,
                        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: C.brand),
                      ),
                    ),
                    Insignia(e.estadoLabel, tono: colorEstado[e.estado]),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  e.nombre,
                  style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: C.text),
                ),
                Meta(Icons.business_outlined, e.cliente),
                if (e.ubicacion != null || e.area != null) Meta(Icons.place_outlined, [e.area, e.ubicacion].whereType<String>().join(' · ')),
              ],
            ),
        ],
      ),
    );
  }
}
