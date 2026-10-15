import { useRef, useState } from 'react';
import { Modal, PanResponder, Pressable, StyleSheet, Text, View, type LayoutChangeEvent } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Svg, { Path } from 'react-native-svg';
import { color } from '@/lib/theme';
import { Boton } from './ui';

type Punto = [number, number];

/** Firma capturada: trazos en coordenadas del pad. Se envía al servidor como JSON y allí se convierte a PNG. */
export interface Firma {
  w: number;
  h: number;
  trazos: Punto[][];
}

export const firmaJson = (f: Firma) => JSON.stringify({ w: Math.round(f.w), h: Math.round(f.h), trazos: f.trazos });

const aPath = (t: Punto[]) =>
  t.map(([x, y], i) => `${i ? 'L' : 'M'}${x.toFixed(1)} ${y.toFixed(1)}`).join(' ') + (t.length === 1 ? ' l0.1 0' : '');

/** Botón que muestra la vista previa de la firma y abre el pad a pantalla completa. */
export function CampoFirma({ titulo, firma, onChange }: { titulo: string; firma: Firma | null; onChange: (f: Firma | null) => void }) {
  const [abierto, setAbierto] = useState(false);

  return (
    <View style={{ marginBottom: 14 }}>
      <Text style={s.label}>{titulo}</Text>
      <Pressable onPress={() => setAbierto(true)} style={s.preview}>
        {firma ? (
          <Svg width="100%" height="100%" viewBox={`0 0 ${firma.w} ${firma.h}`}>
            {firma.trazos.map((t, i) => <Path key={i} d={aPath(t)} stroke={color.text} strokeWidth={3} fill="none" strokeLinecap="round" strokeLinejoin="round" />)}
          </Svg>
        ) : (
          <Text style={{ color: color.brand, fontWeight: '700' }}>✍️  Tocar para firmar</Text>
        )}
      </Pressable>
      {firma ? (
        <Pressable onPress={() => onChange(null)} style={{ alignSelf: 'flex-end', marginTop: 4 }}>
          <Text style={{ color: color.danger, fontSize: 12, fontWeight: '600' }}>Borrar firma</Text>
        </Pressable>
      ) : null}
      <PadModal visible={abierto} titulo={titulo} onCerrar={() => setAbierto(false)} onAceptar={(f) => { onChange(f); setAbierto(false); }} />
    </View>
  );
}

function PadModal({ visible, titulo, onCerrar, onAceptar }: { visible: boolean; titulo: string; onCerrar: () => void; onAceptar: (f: Firma) => void }) {
  const [trazos, setTrazos] = useState<Punto[][]>([]);
  const actual = useRef<Punto[]>([]);
  const tam = useRef({ w: 0, h: 0 });
  const [, forzar] = useState(0);

  const pan = useRef(
    PanResponder.create({
      onStartShouldSetPanResponder: () => true,
      onMoveShouldSetPanResponder: () => true,
      onPanResponderGrant: (e) => {
        actual.current = [[e.nativeEvent.locationX, e.nativeEvent.locationY]];
        forzar((n) => n + 1);
      },
      onPanResponderMove: (e) => {
        actual.current.push([e.nativeEvent.locationX, e.nativeEvent.locationY]);
        forzar((n) => n + 1);
      },
      onPanResponderRelease: () => {
        const t = actual.current;
        actual.current = [];
        if (t.length) setTrazos((prev) => [...prev, t]);
      },
    }),
  ).current;

  const onLayout = (e: LayoutChangeEvent) => {
    tam.current = { w: e.nativeEvent.layout.width, h: e.nativeEvent.layout.height };
  };

  const puntos = trazos.reduce((n, t) => n + t.length, 0);

  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onCerrar} supportedOrientations={['portrait', 'landscape']}>
      <SafeAreaView style={{ flex: 1, backgroundColor: color.bg }}>
        <View style={s.modalHeader}>
          <Text style={s.modalTitulo}>{titulo}</Text>
          <Pressable onPress={() => setTrazos([])}><Text style={{ color: color.danger, fontWeight: '700' }}>Borrar</Text></Pressable>
        </View>
        <View style={s.lienzo} onLayout={onLayout} {...pan.panHandlers}>
          {/* Sin eventos en el SVG: así locationX/Y siempre son relativos al lienzo y no a un trazo */}
          <View pointerEvents="none" style={StyleSheet.absoluteFill}>
            <Svg width="100%" height="100%">
              {[...trazos, actual.current].filter((t) => t.length).map((t, i) => (
                <Path key={i} d={aPath(t)} stroke={color.text} strokeWidth={3} fill="none" strokeLinecap="round" strokeLinejoin="round" />
              ))}
            </Svg>
          </View>
          <View pointerEvents="none" style={s.linea} />
          {!puntos ? <Text pointerEvents="none" style={s.ayuda}>Firme aquí con el dedo</Text> : null}
        </View>
        <View style={{ flexDirection: 'row', gap: 10, padding: 16 }}>
          <Boton style={{ flex: 1 }} variante="secondary" texto="Cancelar" onPress={() => { setTrazos([]); onCerrar(); }} />
          <Boton style={{ flex: 1 }} texto="Aceptar firma" disabled={puntos < 5}
                 onPress={() => { onAceptar({ ...tam.current, trazos }); setTrazos([]); }} />
        </View>
      </SafeAreaView>
    </Modal>
  );
}

const s = StyleSheet.create({
  label: { fontSize: 14, fontWeight: '600', color: color.text, marginBottom: 6 },
  preview: { height: 110, borderWidth: 1.5, borderStyle: 'dashed', borderColor: '#cbd5e1', borderRadius: 12, backgroundColor: '#fff', alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: 16, paddingVertical: 12 },
  modalTitulo: { fontSize: 17, fontWeight: '800', color: color.text },
  lienzo: { flex: 1, marginHorizontal: 16, backgroundColor: '#fff', borderRadius: 16, borderWidth: 1, borderColor: color.border },
  linea: { position: 'absolute', left: 24, right: 24, bottom: '30%', borderBottomWidth: 1, borderColor: '#cbd5e1' },
  ayuda: { position: 'absolute', alignSelf: 'center', top: '40%', color: '#94a3b8', fontSize: 15 },
});
