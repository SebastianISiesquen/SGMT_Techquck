import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../core/api.dart';
import '../widgets/ui.dart';
import 'equipo.dart';

/// Lee el QR del sticker del equipo (o el código de barras de la serie).
/// Con [devolver] regresa el equipo a la pantalla que lo pidió (la hoja de servicio);
/// si no, abre su historial.
class EscanearPantalla extends StatefulWidget {
  final bool devolver;
  const EscanearPantalla({super.key, this.devolver = false});

  @override
  State<EscanearPantalla> createState() => _EscanearPantallaState();
}

class _EscanearPantallaState extends State<EscanearPantalla> {
  final _camara = MobileScannerController(formats: const [BarcodeFormat.qrCode, BarcodeFormat.code128, BarcodeFormat.code39, BarcodeFormat.ean13]);
  bool _buscando = false;
  String _ultimo = '';

  @override
  void dispose() {
    _camara.dispose();
    super.dispose();
  }

  Future<void> _leido(BarcodeCapture captura) async {
    final dato = captura.barcodes.firstOrNull?.rawValue;
    if (dato == null || _buscando || dato == _ultimo) return;
    _ultimo = dato;
    setState(() => _buscando = true);
    try {
      final equipo = await Api.i.equipoPorCodigo(dato);
      if (!mounted) return;
      if (widget.devolver) {
        Navigator.pop(context, equipo);
      } else {
        Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => EquipoPantalla(id: equipo.id)));
      }
    } catch (e) {
      if (!mounted) return;
      await mensaje(context, 'Equipo no encontrado', '$e');
      _ultimo = '';
    } finally {
      if (mounted) setState(() => _buscando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(title: const Text('Escanear equipo')),
      body: Stack(
        children: [
          MobileScanner(
            controller: _camara,
            onDetect: _leido,
            errorBuilder: (context, error) => Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Text(
                  error.errorCode == MobileScannerErrorCode.permissionDenied
                      ? 'Para identificar el equipo, SGGP necesita usar la cámara. Habilite el permiso en los ajustes del teléfono.'
                      : 'No se pudo abrir la cámara (${error.errorCode.name}).',
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Colors.white, fontSize: 16, height: 1.4),
                ),
              ),
            ),
          ),
          // Marco de lectura
          Center(
            child: FractionallySizedBox(
              widthFactor: 0.7,
              child: AspectRatio(
                aspectRatio: 1,
                child: Container(
                  decoration: BoxDecoration(
                    border: Border.all(color: Colors.white, width: 3),
                    borderRadius: BorderRadius.circular(18),
                  ),
                ),
              ),
            ),
          ),
          Positioned(
            left: 20,
            right: 20,
            bottom: 40,
            child: Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(color: const Color(0xD90B1D3A), borderRadius: BorderRadius.circular(14)),
              child: _buscando
                  ? const Center(
                      child: SizedBox(width: 24, height: 24, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5)),
                    )
                  : const Text(
                      'Apunte al QR del sticker del equipo o al código de barras de la serie',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.white, fontSize: 14),
                    ),
            ),
          ),
        ],
      ),
    );
  }
}
