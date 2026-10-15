import { Stack, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { useCallback, useState } from 'react';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Badge, Card, Cargando, Fila } from '@/components/ui';
import { api } from '@/lib/api';
import { color, colorEstado, fecha, tono } from '@/lib/theme';
import type { Equipo } from '@/lib/types';

/** Hoja de vida del equipo: lo que le hicieron otros técnicos antes. */
export default function DetalleEquipo() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const [equipo, setEquipo] = useState<Equipo | null>(null);
  const [cargando, setCargando] = useState(false);

  const cargar = useCallback(async () => {
    setCargando(true);
    try {
      setEquipo(await api.equipo(id));
    } catch (e) {
      Alert.alert('Error', (e as Error).message);
    } finally {
      setCargando(false);
    }
  }, [id]);

  useFocusEffect(useCallback(() => { cargar(); }, [cargar]));

  if (!equipo) return <Cargando />;

  const eventos = equipo.eventos ?? [];
  const fallas = eventos.filter((e) => e.tipo === 'falla').length;
  const ultimoMant = eventos.find((e) => e.tipo === 'mantenimiento');

  return (
    <>
      <Stack.Screen options={{ title: equipo.codigo }} />
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 48 }} refreshControl={<RefreshControl refreshing={cargando} onRefresh={cargar} />}>
        <Text style={s.titulo}>{equipo.nombre}</Text>
        <View style={{ flexDirection: 'row', gap: 8, marginBottom: 14, flexWrap: 'wrap' }}>
          <Badge texto={equipo.estado_label} tono={colorEstado[equipo.estado]} />
          {equipo.garantia
            ? <Badge texto={`Garantía: ${equipo.garantia.dias_restantes} días`} tono="green" />
            : <Badge texto="Sin garantía vigente" tono="red" />}
        </View>

        <View style={s.resumen}>
          <Dato valor={String(eventos.length)} texto="Registros" />
          <Dato valor={String(fallas)} texto="Fallas" tono={fallas ? color.danger : undefined} />
          <Dato valor={ultimoMant ? fecha(ultimoMant.fecha, false) : '—'} texto="Últ. mantenimiento" />
        </View>

        <Card titulo="Datos del equipo">
          <Fila label="Cliente" valor={equipo.cliente} />
          <Fila label="Ubicación" valor={[equipo.area, equipo.ubicacion].filter(Boolean).join(' · ')} />
          <Fila label="Responsable / usuario" valor={equipo.responsable} />
          <Fila label="N° de serie" valor={equipo.serie} />
          <Fila label="Proyecto / servicio" valor={equipo.proyecto ? `${equipo.proyecto.codigo} · ${equipo.proyecto.nombre}` : null} />
          {equipo.garantia ? <Fila label="Garantía" valor={`${equipo.garantia.codigo} · vence ${fecha(equipo.garantia.fecha_vencimiento, false)}`} /> : null}
        </Card>

        <Card titulo={`Historial (${eventos.length})`}>
          {eventos.length ? eventos.map((e, i) => {
            const t = tono[e.color] ?? tono.slate;
            return (
              <View key={e.id} style={[s.evento, i === eventos.length - 1 && { borderLeftColor: 'transparent' }]}>
                <View style={[s.punto, { backgroundColor: t.fg }]} />
                <View style={{ flex: 1, paddingBottom: 14 }}>
                  <View style={{ flexDirection: 'row', gap: 6, alignItems: 'center', flexWrap: 'wrap' }}>
                    <Badge texto={e.tipo_label} tono={e.color} />
                    <Text style={s.meta}>{fecha(e.fecha)}</Text>
                  </View>
                  <Text style={s.eventoTitulo}>{e.titulo}</Text>
                  {e.componente ? <Text style={{ color: tono.violet.fg, fontSize: 13 }}>Componente: {e.componente}</Text> : null}
                  {e.detalle ? <Text style={s.detalle}>{e.detalle}</Text> : null}
                  <Text style={s.meta}>{[e.usuario ?? 'Sistema', e.incidencia, e.hoja].filter(Boolean).join(' · ')}</Text>
                </View>
              </View>
            );
          }) : <Text style={{ color: color.textMuted }}>Aún no hay registros.</Text>}
        </Card>
      </ScrollView>
    </>
  );
}

function Dato({ valor, texto, tono: t }: { valor: string; texto: string; tono?: string }) {
  return (
    <View style={s.dato}>
      <Text style={[s.datoValor, t ? { color: t } : null]}>{valor}</Text>
      <Text style={s.datoTexto}>{texto}</Text>
    </View>
  );
}

const s = StyleSheet.create({
  titulo: { fontSize: 19, fontWeight: '800', color: color.text, marginBottom: 8 },
  resumen: { flexDirection: 'row', gap: 8, marginBottom: 12 },
  dato: { flex: 1, backgroundColor: '#fff', borderRadius: 12, borderWidth: 1, borderColor: color.border, padding: 10 },
  datoValor: { fontSize: 16, fontWeight: '800', color: color.text },
  datoTexto: { fontSize: 11, color: color.textMuted, marginTop: 2 },
  evento: { flexDirection: 'row', gap: 10, borderLeftWidth: 2, borderLeftColor: color.border, marginLeft: 5, paddingLeft: 12 },
  punto: { position: 'absolute', left: -7, top: 3, width: 12, height: 12, borderRadius: 6, borderWidth: 2, borderColor: '#fff' },
  eventoTitulo: { fontSize: 14, fontWeight: '700', color: color.text, marginTop: 6 },
  detalle: { fontSize: 13, color: '#334155', marginTop: 4, lineHeight: 19 },
  meta: { fontSize: 12, color: color.textMuted, marginTop: 4 },
});
