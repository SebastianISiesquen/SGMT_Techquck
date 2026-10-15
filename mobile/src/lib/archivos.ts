import * as DocumentPicker from 'expo-document-picker';
import * as ImagePicker from 'expo-image-picker';
import { ImageManipulator, SaveFormat } from 'expo-image-manipulator';
import { Alert } from 'react-native';

export interface ArchivoLocal {
  uri: string;
  nombre: string;
  mime: string;
  esImagen: boolean;
  categoria: 'foto' | 'acta_conformidad' | 'informe_tecnico' | 'documento';
  descripcion: string;
}

const ANCHO_MAX = 1600;

/** Reduce las fotos (las cámaras actuales generan 5–10 MB) antes de subirlas. */
async function comprimir(uri: string, ancho: number): Promise<string> {
  const ctx = ImageManipulator.manipulate(uri);
  if (ancho > ANCHO_MAX) ctx.resize({ width: ANCHO_MAX, height: null });
  const img = await ctx.renderAsync();
  const r = await img.saveAsync({ format: SaveFormat.JPEG, compress: 0.7 });
  return r.uri;
}

async function deImagenes(assets: ImagePicker.ImagePickerAsset[]): Promise<ArchivoLocal[]> {
  return Promise.all(
    assets.map(async (a, i) => ({
      uri: await comprimir(a.uri, a.width || ANCHO_MAX + 1),
      nombre: `evidencia_${Date.now()}_${i}.jpg`,
      mime: 'image/jpeg',
      esImagen: true,
      categoria: 'foto' as const,
      descripcion: '',
    })),
  );
}

export async function tomarFoto(): Promise<ArchivoLocal[]> {
  const permiso = await ImagePicker.requestCameraPermissionsAsync();
  if (!permiso.granted) {
    Alert.alert('Permiso requerido', 'Habilite el acceso a la cámara en los ajustes del teléfono.');
    return [];
  }
  const r = await ImagePicker.launchCameraAsync({ mediaTypes: 'images', quality: 0.9 });
  return r.canceled ? [] : deImagenes(r.assets);
}

export async function elegirDeGaleria(): Promise<ArchivoLocal[]> {
  const r = await ImagePicker.launchImageLibraryAsync({
    mediaTypes: 'images',
    allowsMultipleSelection: true,
    selectionLimit: 10,
    quality: 0.9,
  });
  return r.canceled ? [] : deImagenes(r.assets);
}

/** Actas e informes: PDF, Word, Excel o imagen escaneada. */
export async function elegirDocumento(): Promise<ArchivoLocal[]> {
  const r = await DocumentPicker.getDocumentAsync({
    type: [
      'application/pdf',
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'application/vnd.ms-excel',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'image/*',
    ],
    multiple: true,
    copyToCacheDirectory: true,
  });
  if (r.canceled) return [];

  return r.assets.map((a) => ({
    uri: a.uri,
    nombre: a.name,
    mime: a.mimeType ?? 'application/octet-stream',
    esImagen: (a.mimeType ?? '').startsWith('image/'),
    categoria: 'acta_conformidad' as const,
    descripcion: '',
  }));
}

/** En React Native, FormData acepta { uri, name, type } para adjuntar archivos locales. */
export function adjuntar(form: FormData, campo: string, a: ArchivoLocal) {
  form.append(campo, { uri: a.uri, name: a.nombre, type: a.mime } as unknown as Blob);
}
