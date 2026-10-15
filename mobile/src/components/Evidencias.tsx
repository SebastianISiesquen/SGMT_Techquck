import { Image, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { elegirDeGaleria, elegirDocumento, tomarFoto, type ArchivoLocal } from '@/lib/archivos';
import { color } from '@/lib/theme';
import { Chip } from './ui';

const CATEGORIAS: { valor: ArchivoLocal['categoria']; texto: string }[] = [
  { valor: 'foto', texto: 'Foto' },
  { valor: 'acta_conformidad', texto: 'Acta' },
  { valor: 'informe_tecnico', texto: 'Informe' },
  { valor: 'documento', texto: 'Otro' },
];

export function Evidencias({ items, onChange }: { items: ArchivoLocal[]; onChange: (items: ArchivoLocal[]) => void }) {
  const agregar = async (fuente: () => Promise<ArchivoLocal[]>) => {
    const nuevos = await fuente();
    if (nuevos.length) onChange([...items, ...nuevos]);
  };

  const actualizar = (i: number, cambios: Partial<ArchivoLocal>) =>
    onChange(items.map((it, j) => (j === i ? { ...it, ...cambios } : it)));

  return (
    <View>
      <View style={s.acciones}>
        <Accion icono="📷" texto="Tomar foto" onPress={() => agregar(tomarFoto)} />
        <Accion icono="🖼️" texto="Galería" onPress={() => agregar(elegirDeGaleria)} />
        <Accion icono="📄" texto="Acta / PDF / Word" onPress={() => agregar(elegirDocumento)} />
      </View>

      {items.map((it, i) => (
        <View key={it.uri + i} style={s.item}>
          <View style={{ flexDirection: 'row', gap: 10, alignItems: 'center' }}>
            {it.esImagen ? (
              <Image source={{ uri: it.uri }} style={s.thumb} />
            ) : (
              <View style={[s.thumb, s.doc]}><Text style={s.docExt}>{it.nombre.split('.').pop()?.toUpperCase()}</Text></View>
            )}
            <Text style={{ flex: 1, fontSize: 13, color: color.text }} numberOfLines={2}>{it.nombre}</Text>
            <Pressable onPress={() => onChange(items.filter((_, j) => j !== i))} hitSlop={10}>
              <Text style={{ color: color.danger, fontWeight: '700' }}>Quitar</Text>
            </Pressable>
          </View>
          <View style={s.cats}>
            {CATEGORIAS.map((c) => (
              <Chip key={c.valor} texto={c.texto} activo={it.categoria === c.valor} onPress={() => actualizar(i, { categoria: c.valor })} />
            ))}
          </View>
          <TextInput
            value={it.descripcion}
            onChangeText={(t) => actualizar(i, { descripcion: t })}
            placeholder="Descripción (opcional)"
            placeholderTextColor="#94a3b8"
            style={s.desc}
          />
        </View>
      ))}
    </View>
  );
}

function Accion({ icono, texto, onPress }: { icono: string; texto: string; onPress: () => void }) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [s.accion, pressed && { backgroundColor: color.brandSoft }]}>
      <Text style={{ fontSize: 22 }}>{icono}</Text>
      <Text style={s.accionText}>{texto}</Text>
    </Pressable>
  );
}

const s = StyleSheet.create({
  acciones: { flexDirection: 'row', gap: 8, marginBottom: 12 },
  accion: { flex: 1, alignItems: 'center', gap: 4, borderWidth: 1.5, borderStyle: 'dashed', borderColor: '#cbd5e1', borderRadius: 12, paddingVertical: 14 },
  accionText: { fontSize: 12, fontWeight: '600', color: color.brand, textAlign: 'center' },
  item: { borderWidth: 1, borderColor: color.border, borderRadius: 12, padding: 10, marginBottom: 10, backgroundColor: '#fff' },
  thumb: { width: 54, height: 54, borderRadius: 8 },
  doc: { backgroundColor: '#fef2f2', alignItems: 'center', justifyContent: 'center' },
  docExt: { fontSize: 11, fontWeight: '800', color: '#b91c1c' },
  cats: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginTop: 10 },
  desc: { marginTop: 8, borderWidth: 1, borderColor: color.border, borderRadius: 8, paddingHorizontal: 10, paddingVertical: 8, fontSize: 13, color: color.text },
});
