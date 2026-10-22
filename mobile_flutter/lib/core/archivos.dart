import 'package:file_picker/file_picker.dart';
import 'package:image_picker/image_picker.dart';

/// Archivo elegido en el teléfono, listo para subirse.
class ArchivoLocal {
  final String ruta;
  final String nombre;
  final String mime;
  final bool esImagen;
  String categoria; // foto | acta_conformidad | informe_tecnico | documento
  String descripcion;

  ArchivoLocal({
    required this.ruta,
    required this.nombre,
    required this.mime,
    required this.esImagen,
    this.categoria = 'foto',
    this.descripcion = '',
  });
}

final _picker = ImagePicker();

/// Las cámaras actuales generan fotos de 5–10 MB: se reducen a 1600 px y calidad 70 antes de subirlas.
const _anchoMax = 1600.0;
const _calidad = 70;

ArchivoLocal _deImagen(XFile f, int i) =>
    ArchivoLocal(ruta: f.path, nombre: 'evidencia_${DateTime.now().millisecondsSinceEpoch}_$i.jpg', mime: 'image/jpeg', esImagen: true);

Future<List<ArchivoLocal>> tomarFoto() async {
  final f = await _picker.pickImage(source: ImageSource.camera, maxWidth: _anchoMax, imageQuality: _calidad);
  return f == null ? [] : [_deImagen(f, 0)];
}

Future<List<ArchivoLocal>> elegirDeGaleria() async {
  final fotos = await _picker.pickMultiImage(maxWidth: _anchoMax, imageQuality: _calidad, limit: 10);
  return [for (var i = 0; i < fotos.length; i++) _deImagen(fotos[i], i)];
}

const _mimes = {
  'pdf': 'application/pdf',
  'doc': 'application/msword',
  'docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'xls': 'application/vnd.ms-excel',
  'xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'jpg': 'image/jpeg',
  'jpeg': 'image/jpeg',
  'png': 'image/png',
};

/// Actas e informes: PDF, Word, Excel o imagen escaneada.
Future<List<ArchivoLocal>> elegirDocumento() async {
  final r = await FilePicker.pickFiles(type: FileType.custom, allowedExtensions: _mimes.keys.toList());
  return r.where((f) => f.path != null).map((f) {
    final mime = _mimes[(f.extension ?? '').toLowerCase()] ?? 'application/octet-stream';
    return ArchivoLocal(ruta: f.path!, nombre: f.name, mime: mime, esImagen: mime.startsWith('image/'), categoria: 'acta_conformidad');
  }).toList();
}
