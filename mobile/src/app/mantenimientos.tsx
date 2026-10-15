import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { Alert, FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { Badge } from '@/components/ui';
import { api } from '@/lib/api';
import { color, fecha } from '@/lib/theme';
import type { Mantenimiento } from '@/lib/types';

/** Visitas de mantenimiento preventivo asignadas al técnico (próximos 60 días y atrasadas). */
export default function Mantenimientos() {
  const router = useRouter();
  const [items, setItems] = useState<Mantenimiento[]>([]);
  const [cargando, setCargando] = useState(false);

  const cargar = useCallback(async () => {
    setCargando(true);
    try {
      setItems(await api.mantenimientos());
    } catch (e) {
      Alert.alert('Error', (e as Error).message);
    } finally {
      setCargando(false);
    }
  }, []);

  useFocusEffect(useCallback(() => { cargar(); }, [cargar]));

  return (
    <FlatList
      data={items}
      keyExtractor={(m) => String(m.id)}
      contentContainerStyle={{ padding: 16, paddingBottom: 40 }}
      refreshControl={<RefreshControl refreshing={cargando} onRefresh={cargar} />}
      ListEmptyComponent={!cargando ? <Text style={s.vacio}>No tiene mantenimientos programados en los próximos 60 días. ✅</Text> : null}
      renderItem={({ item }) => (
        <View style={s.item}>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
            <Text style={s.fecha}>{fecha(item.fecha_programada, false)}</Text>
            <Badge texto={item.atrasado ? 'Atrasado' : item.dias_restantes === 0 ? 'Hoy' : `En ${item.dias_restantes} días`}
                   tono={item.atrasado ? 'red' : item.dias_restantes <= 7 ? 'amber' : 'blue'} />
          </View>
          <Text style={s.titulo}>{item.etiqueta}</Text>
          <Text style={s.meta}>{item.plan}</Text>
          <Text style={s.meta}>🏢 {item.cliente}</Text>
          {item.alcance ? <Text style={[s.meta, { marginTop: 6 }]} numberOfLines={3}>{item.alcance}</Text> : null}
          <Pressable
            onPress={() => router.push({ pathname: '/hojas/nueva', params: { mantenimiento_id: String(item.id), proyecto_id: String(item.proyecto.id), etiqueta: item.etiqueta } })}
            style={s.boton}
          >
            <Text style={{ color: '#fff', fontWeight: '700' }}>📋  Registrar hoja de servicio</Text>
          </Pressable>
        </View>
      )}
    />
  );
}

const s = StyleSheet.create({
  item: { backgroundColor: '#fff', borderRadius: 14, borderWidth: 1, borderColor: color.border, padding: 14, marginBottom: 10 },
  fecha: { fontSize: 15, fontWeight: '800', color: color.text },
  titulo: { fontSize: 15, fontWeight: '700', color: color.text, marginTop: 8 },
  meta: { fontSize: 13, color: color.textMuted, marginTop: 2 },
  boton: { backgroundColor: color.brand, borderRadius: 10, minHeight: 44, alignItems: 'center', justifyContent: 'center', marginTop: 12 },
  vacio: { textAlign: 'center', color: color.textMuted, marginTop: 40 },
});
