import { CameraView, useCameraPermissions } from 'expo-camera';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useRef, useState } from 'react';
import { ActivityIndicator, Alert, StyleSheet, Text, View } from 'react-native';
import { Boton } from '@/components/ui';
import { api } from '@/lib/api';
import { dejarEquipoEscaneado } from '@/lib/seleccion';
import { color } from '@/lib/theme';

/**
 * Lee el QR del sticker del equipo (o el código de barras de la serie).
 * Con ?para=hoja devuelve el equipo a la hoja de servicio; si no, abre su historial.
 */
export default function Escanear() {
  const { para } = useLocalSearchParams<{ para?: string }>();
  const router = useRouter();
  const [permiso, pedirPermiso] = useCameraPermissions();
  const [buscando, setBuscando] = useState(false);
  const ultimo = useRef('');

  if (!permiso) return <View style={s.fondo} />;

  if (!permiso.granted) {
    return (
      <View style={[s.fondo, { padding: 24, justifyContent: 'center' }]}>
        <Text style={s.mensaje}>Para identificar el equipo, SGGP necesita usar la cámara y leer el código QR del sticker.</Text>
        <Boton texto="Permitir cámara" onPress={pedirPermiso} />
      </View>
    );
  }

  const leido = async ({ data }: { data: string }) => {
    if (buscando || data === ultimo.current) return;
    ultimo.current = data;
    setBuscando(true);
    try {
      const equipo = await api.equipoPorCodigo(data);
      if (para === 'hoja') {
        dejarEquipoEscaneado(equipo);
        router.back();
      } else {
        router.replace(`/equipos/${equipo.id}`);
      }
    } catch (e) {
      Alert.alert('Equipo no encontrado', (e as Error).message, [{ text: 'Reintentar', onPress: () => { ultimo.current = ''; } }]);
    } finally {
      setBuscando(false);
    }
  };

  return (
    <View style={s.fondo}>
      <CameraView
        style={StyleSheet.absoluteFill}
        facing="back"
        barcodeScannerSettings={{ barcodeTypes: ['qr', 'code128', 'code39', 'ean13'] }}
        onBarcodeScanned={buscando ? undefined : leido}
      />
      <View style={s.marco} pointerEvents="none" />
      <View style={s.pie}>
        {buscando ? <ActivityIndicator color="#fff" /> : <Text style={s.ayuda}>Apunte al QR del sticker del equipo o al código de barras de la serie</Text>}
      </View>
    </View>
  );
}

const s = StyleSheet.create({
  fondo: { flex: 1, backgroundColor: '#000' },
  mensaje: { color: '#fff', fontSize: 16, textAlign: 'center', marginBottom: 20, lineHeight: 22 },
  marco: { position: 'absolute', top: '25%', left: '15%', width: '70%', aspectRatio: 1, borderWidth: 3, borderColor: '#fff', borderRadius: 18 },
  pie: { position: 'absolute', bottom: 40, left: 20, right: 20, backgroundColor: 'rgba(11,29,58,0.85)', borderRadius: 14, padding: 14, alignItems: 'center' },
  ayuda: { color: '#fff', textAlign: 'center', fontSize: 14 },
});
