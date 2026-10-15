import { useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { Badge, Boton } from '@/components/ui';
import { api } from '@/lib/api';
import { color, colorEstado } from '@/lib/theme';
import type { Equipo } from '@/lib/types';

/** Buscar un equipo por serie, código del sticker, marca o modelo; o escanear su QR. */
export default function BuscarEquipos() {
  const router = useRouter();
  const [texto, setTexto] = useState('');
  const [items, setItems] = useState<Equipo[] | null>(null);
  const [cargando, setCargando] = useState(false);
  const [error, setError] = useState('');

  const buscar = async () => {
    if (texto.trim().length < 2) return;
    setCargando(true);
    setError('');
    try {
      setItems(await api.buscarEquipos(texto.trim()));
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setCargando(false);
    }
  };

  return (
    <FlatList
      data={items ?? []}
      keyExtractor={(e) => String(e.id)}
      contentContainerStyle={{ padding: 16, paddingBottom: 40 }}
      keyboardShouldPersistTaps="handled"
      ListHeaderComponent={
        <View>
          <Boton texto="📷  Escanear QR del equipo" onPress={() => router.push('/equipos/escanear')} style={{ marginBottom: 14 }} />
          <View style={s.buscador}>
            <TextInput value={texto} onChangeText={setTexto} onSubmitEditing={buscar} returnKeyType="search" autoCapitalize="characters"
                       placeholder="Serie, código EQ-…, marca o modelo" placeholderTextColor="#94a3b8" style={s.input} />
            <Pressable onPress={buscar} style={s.lupa}><Text style={{ color: '#fff', fontWeight: '700' }}>Buscar</Text></Pressable>
          </View>
          {cargando ? <ActivityIndicator style={{ marginVertical: 16 }} color={color.brand} /> : null}
          {error ? <Text style={s.error}>{error}</Text> : null}
          {items && !items.length && !cargando ? <Text style={s.vacio}>No se encontraron equipos.</Text> : null}
          {!items ? <Text style={s.vacio}>Conozca el historial de un equipo antes de atenderlo: mantenimientos, fallas, diagnósticos y cambios de componentes.</Text> : null}
        </View>
      }
      renderItem={({ item }) => (
        <Pressable onPress={() => router.push(`/equipos/${item.id}`)} style={({ pressed }) => [s.item, pressed && { opacity: 0.85 }]}>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
            <Text style={s.codigo}>{item.codigo}</Text>
            <Badge texto={item.estado_label} tono={colorEstado[item.estado]} />
          </View>
          <Text style={s.titulo}>{item.nombre}</Text>
          <Text style={s.meta}>🏢 {item.cliente}</Text>
          {item.ubicacion || item.area ? <Text style={s.meta}>📍 {[item.area, item.ubicacion].filter(Boolean).join(' · ')}</Text> : null}
        </Pressable>
      )}
    />
  );
}

const s = StyleSheet.create({
  buscador: { flexDirection: 'row', gap: 8, marginBottom: 12 },
  input: { flex: 1, backgroundColor: '#fff', borderWidth: 1, borderColor: color.border, borderRadius: 12, paddingHorizontal: 12, fontSize: 15, minHeight: 48 },
  lupa: { backgroundColor: color.brandDark, borderRadius: 12, paddingHorizontal: 16, justifyContent: 'center' },
  item: { backgroundColor: '#fff', borderRadius: 14, borderWidth: 1, borderColor: color.border, padding: 14, marginBottom: 10 },
  codigo: { fontSize: 12, fontWeight: '700', color: color.brand },
  titulo: { fontSize: 15, fontWeight: '700', color: color.text, marginTop: 6, marginBottom: 2 },
  meta: { fontSize: 13, color: color.textMuted, marginTop: 2 },
  vacio: { textAlign: 'center', color: color.textMuted, marginTop: 20, lineHeight: 20 },
  error: { color: color.danger, backgroundColor: '#fef2f2', padding: 10, borderRadius: 8, marginBottom: 12 },
});
