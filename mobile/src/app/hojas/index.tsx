import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { Badge, Boton } from '@/components/ui';
import { api } from '@/lib/api';
import { color, colorEstado, fecha } from '@/lib/theme';
import type { HojaServicio } from '@/lib/types';

export default function MisHojas() {
  const router = useRouter();
  const [hojas, setHojas] = useState<HojaServicio[]>([]);
  const [cargando, setCargando] = useState(false);
  const [error, setError] = useState('');

  const cargar = useCallback(async () => {
    setCargando(true);
    setError('');
    try {
      setHojas(await api.hojas());
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setCargando(false);
    }
  }, []);

  useFocusEffect(useCallback(() => { cargar(); }, [cargar]));

  return (
    <FlatList
      data={hojas}
      keyExtractor={(h) => String(h.id)}
      contentContainerStyle={{ padding: 16, paddingBottom: 40 }}
      refreshControl={<RefreshControl refreshing={cargando} onRefresh={cargar} />}
      ListHeaderComponent={
        <View style={{ marginBottom: 14 }}>
          <Boton texto="＋  Nueva hoja (preventivo / inspección)" onPress={() => router.push('/hojas/nueva')} />
          <Text style={s.ayuda}>Para un servicio correctivo, cree la hoja desde la incidencia.</Text>
          {error ? <Text style={s.error}>{error}</Text> : null}
        </View>
      }
      ListEmptyComponent={!cargando ? <Text style={s.vacio}>Aún no registra hojas de servicio.</Text> : null}
      renderItem={({ item }) => (
        <Pressable onPress={() => router.push(`/hojas/${item.id}`)} style={({ pressed }) => [s.item, pressed && { opacity: 0.85 }]}>
          <View style={s.fila}>
            <Text style={s.numero}>{item.numero}</Text>
            <Badge texto={item.estado_label} tono={colorEstado[item.estado]} />
          </View>
          <Text style={s.titulo}>{item.tipo_servicio_label}</Text>
          <Text style={s.meta}>🏛️ {item.proyecto.entidad}</Text>
          <Text style={s.meta} numberOfLines={1}>📁 {item.proyecto.nombre}</Text>
          <Text style={s.meta}>📅 {fecha(item.fecha, false)} · {item.hora_llegada}{item.hora_salida ? `–${item.hora_salida}` : ''}</Text>
        </Pressable>
      )}
    />
  );
}

const s = StyleSheet.create({
  ayuda: { fontSize: 12, color: color.textMuted, textAlign: 'center', marginTop: 8 },
  error: { color: color.danger, backgroundColor: '#fef2f2', padding: 10, borderRadius: 8, marginTop: 12 },
  vacio: { textAlign: 'center', color: color.textMuted, marginTop: 30 },
  item: { backgroundColor: '#fff', borderRadius: 14, borderWidth: 1, borderColor: color.border, padding: 14, marginBottom: 10 },
  fila: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  numero: { fontSize: 12, color: color.textMuted, fontWeight: '600' },
  titulo: { fontSize: 16, fontWeight: '700', color: color.text, marginTop: 6, marginBottom: 4 },
  meta: { fontSize: 13, color: color.textMuted, marginTop: 2 },
});
