import { Stack, useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { Badge, Chip } from '@/components/ui';
import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { color, colorEstado, colorPrioridad, fecha } from '@/lib/theme';
import type { Incidencia, Resumen } from '@/lib/types';

const FILTROS = [
  { valor: 'abiertas', texto: 'Pendientes' },
  { valor: 'resuelta', texto: 'Resueltas' },
  { valor: 'cerrada', texto: 'Cerradas' },
];

export default function Bandeja() {
  const router = useRouter();
  const { usuario } = useAuth();
  const [filtro, setFiltro] = useState('abiertas');
  const [items, setItems] = useState<Incidencia[]>([]);
  const [resumen, setResumen] = useState<Resumen | null>(null);
  const [cargando, setCargando] = useState(false);
  const [error, setError] = useState('');

  const cargar = useCallback(async (f = filtro) => {
    setCargando(true);
    setError('');
    try {
      const [lista, res] = await Promise.all([api.incidencias(f), api.resumen()]);
      setItems(lista);
      setResumen(res);
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setCargando(false);
    }
  }, [filtro]);

  // Recarga al volver a la pantalla (p. ej. después de registrar una atención).
  useFocusEffect(useCallback(() => { cargar(); }, [cargar]));

  return (
    <>
      <Stack.Screen
        options={{
          headerRight: () => (
            <Pressable onPress={() => router.push('/perfil')} style={s.avatar} hitSlop={8}>
              <Text style={s.avatarText}>{usuario?.iniciales}</Text>
              {usuario?.no_leidas ? <View style={s.punto} /> : null}
            </Pressable>
          ),
        }}
      />
      <FlatList
        data={items}
        keyExtractor={(i) => String(i.id)}
        contentContainerStyle={{ padding: 16, paddingBottom: 40 }}
        refreshControl={<RefreshControl refreshing={cargando} onRefresh={() => cargar()} />}
        ListHeaderComponent={
          <View>
            <Text style={s.saludo}>Hola, {usuario?.nombre.split(' ')[0]}</Text>
            <View style={s.accesos}>
              <Acceso icono="📷" texto="Equipos" detalle="Escanear o buscar" onPress={() => router.push('/equipos')} />
              <Acceso icono="🗓️" texto="Mantenimientos" detalle="Programados" onPress={() => router.push('/mantenimientos')} />
              <Acceso icono="📋" texto="Hojas" detalle="De servicio" onPress={() => router.push('/hojas')} />
            </View>
            {resumen ? (
              <View style={s.resumen}>
                <Kpi valor={resumen.asignadas} texto="Asignadas" tono={color.brand} />
                <Kpi valor={resumen.en_proceso} texto="En proceso" tono="#b45309" />
                <Kpi valor={resumen.criticas} texto="Alta / crítica" tono={color.danger} />
              </View>
            ) : null}
            <View style={s.filtros}>
              {FILTROS.map((f) => (
                <Chip key={f.valor} texto={f.texto} activo={filtro === f.valor} onPress={() => { setFiltro(f.valor); cargar(f.valor); }} />
              ))}
            </View>
            {error ? <Text style={s.error}>{error}</Text> : null}
          </View>
        }
        ListEmptyComponent={!cargando ? <Text style={s.vacio}>No tiene incidencias en esta bandeja. ✅</Text> : null}
        renderItem={({ item }) => (
          <Pressable onPress={() => router.push(`/incidencia/${item.id}`)} style={({ pressed }) => [s.item, pressed && { opacity: 0.85 }]}>
            <View style={s.itemTop}>
              <Text style={s.codigo}>{item.codigo}</Text>
              <Badge texto={item.prioridad_label} tono={colorPrioridad[item.prioridad]} />
            </View>
            <Text style={s.titulo}>{item.titulo}</Text>
            <Text style={s.meta}>🏛️ {item.proyecto.entidad}</Text>
            {item.ubicacion_detalle ? <Text style={s.meta}>📍 {item.ubicacion_detalle}</Text> : null}
            {item.proxima_visita ? <Text style={[s.meta, { color: '#1d4ed8', fontWeight: '700' }]}>🗓️ Próxima visita: {fecha(item.proxima_visita, false)}</Text> : null}
            <View style={s.itemBottom}>
              <Badge texto={item.estado_label} tono={colorEstado[item.estado]} />
              <Text style={[s.meta, item.sla_vencido && { color: color.danger, fontWeight: '700' }]}>
                {item.sla_vencido ? 'Fuera de plazo' : fecha(item.fecha_reporte)}
              </Text>
            </View>
          </Pressable>
        )}
      />
    </>
  );
}

function Acceso({ icono, texto, detalle, onPress }: { icono: string; texto: string; detalle: string; onPress: () => void }) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [s.acceso, pressed && { opacity: 0.85 }]}>
      <Text style={{ fontSize: 22 }}>{icono}</Text>
      <Text style={{ fontWeight: '700', color: color.text, marginTop: 4 }}>{texto}</Text>
      <Text style={{ fontSize: 11, color: color.textMuted }}>{detalle}</Text>
    </Pressable>
  );
}

function Kpi({ valor, texto, tono }: { valor: number; texto: string; tono: string }) {
  return (
    <View style={s.kpi}>
      <Text style={[s.kpiValor, { color: tono }]}>{valor}</Text>
      <Text style={s.kpiTexto}>{texto}</Text>
    </View>
  );
}

const s = StyleSheet.create({
  saludo: { fontSize: 22, fontWeight: '800', color: color.text, marginBottom: 12 },
  accesos: { flexDirection: 'row', gap: 10, marginBottom: 12 },
  acceso: { flex: 1, backgroundColor: '#fff', borderRadius: 14, borderWidth: 1, borderColor: color.border, padding: 12 },
  resumen: { flexDirection: 'row', gap: 10, marginBottom: 16 },
  kpi: { flex: 1, backgroundColor: '#fff', borderRadius: 14, borderWidth: 1, borderColor: color.border, padding: 12 },
  kpiValor: { fontSize: 26, fontWeight: '800' },
  kpiTexto: { fontSize: 12, color: color.textMuted, marginTop: 2 },
  filtros: { flexDirection: 'row', gap: 8, marginBottom: 14 },
  item: { backgroundColor: '#fff', borderRadius: 14, borderWidth: 1, borderColor: color.border, padding: 14, marginBottom: 10 },
  itemTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  itemBottom: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 10 },
  codigo: { fontSize: 12, color: color.textMuted },
  titulo: { fontSize: 16, fontWeight: '700', color: color.text, marginTop: 6, marginBottom: 4 },
  meta: { fontSize: 13, color: color.textMuted, marginTop: 2 },
  vacio: { textAlign: 'center', color: color.textMuted, marginTop: 40 },
  error: { color: color.danger, backgroundColor: '#fef2f2', padding: 10, borderRadius: 8, marginBottom: 12 },
  avatar: { width: 34, height: 34, borderRadius: 17, backgroundColor: color.brand, alignItems: 'center', justifyContent: 'center' },
  avatarText: { color: '#fff', fontWeight: '700', fontSize: 13 },
  punto: { position: 'absolute', top: 0, right: 0, width: 10, height: 10, borderRadius: 5, backgroundColor: '#ef4444', borderWidth: 2, borderColor: color.brandDark },
});
