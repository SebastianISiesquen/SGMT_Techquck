import { Stack, useFocusEffect, useLocalSearchParams, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { Alert, Image, Linking, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Badge, Boton, Card, Cargando, Fila } from '@/components/ui';
import { api, authHeaders } from '@/lib/api';
import { adjuntar, elegirDocumento, tomarFoto, type ArchivoLocal } from '@/lib/archivos';
import { color, colorEstado, fecha } from '@/lib/theme';
import type { HojaServicio } from '@/lib/types';

/** Detalle de la hoja: PDF para imprimir y subida del acta firmada por el cliente. */
export default function DetalleHoja() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const [hoja, setHoja] = useState<HojaServicio | null>(null);
  const [cargando, setCargando] = useState(false);
  const [subiendo, setSubiendo] = useState(false);

  const cargar = useCallback(async () => {
    setCargando(true);
    try {
      setHoja(await api.hoja(id));
    } catch (e) {
      Alert.alert('Error', (e as Error).message);
    } finally {
      setCargando(false);
    }
  }, [id]);

  useFocusEffect(useCallback(() => { cargar(); }, [cargar]));

  if (!hoja) return <Cargando />;

  const subirActa = async (fuente: () => Promise<ArchivoLocal[]>) => {
    const archivos = await fuente();
    if (!archivos.length) return;
    setSubiendo(true);
    try {
      const form = new FormData();
      archivos.forEach((a, i) => adjuntar(form, `actas[${i}]`, a));
      setHoja(await api.subirActa(hoja.id, form));
      Alert.alert('Acta registrada', 'La hoja quedó con el acta de conformidad del cliente.');
    } catch (e) {
      Alert.alert('No se pudo subir', (e as Error).message);
    } finally {
      setSubiendo(false);
    }
  };

  const actas = (hoja.archivos ?? []).filter((a) => a.categoria === 'acta_conformidad');
  const fotos = (hoja.archivos ?? []).filter((a) => a.es_imagen && a.categoria !== 'acta_conformidad');

  return (
    <>
      <Stack.Screen options={{ title: hoja.numero }} />
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 48 }} refreshControl={<RefreshControl refreshing={cargando} onRefresh={cargar} />}>
        <View style={{ flexDirection: 'row', gap: 8, marginBottom: 12, flexWrap: 'wrap' }}>
          <Badge texto={hoja.estado_label} tono={hoja.estado === 'registrada' ? 'amber' : colorEstado[hoja.estado]} />
          {hoja.resultado_label ? <Badge texto={hoja.resultado_label} tono={colorEstado[hoja.resultado ?? ''] ?? 'slate'} /> : null}
        </View>

        {hoja.proxima_visita ? (
          <View style={s.continua}>
            <Text style={{ fontWeight: '700', color: '#1e3a8a' }}>🗓️ Continúa el {fecha(hoja.proxima_visita, false)}</Text>
            {hoja.pendientes ? <Text style={{ color: '#1e3a8a', marginTop: 2 }}>Pendiente: {hoja.pendientes}</Text> : null}
          </View>
        ) : null}

        {/* Siguiente paso: el acta firmada */}
        {!hoja.tiene_acta && hoja.estado !== 'anulada' ? (
          <View style={s.pendiente}>
            <Text style={{ fontWeight: '800', color: '#92400e', marginBottom: 4 }}>Falta el acta de conformidad</Text>
            <Text style={{ color: '#92400e', fontSize: 13, marginBottom: 10 }}>Imprima o muestre la hoja, que el cliente la firme y selle, y suba una foto o el PDF del acta firmada.</Text>
            {hoja.puede_subir_acta ? (
              <View style={{ flexDirection: 'row', gap: 8 }}>
                <Boton style={{ flex: 1 }} variante="success" texto="📷 Foto del acta" onPress={() => subirActa(tomarFoto)} cargando={subiendo} />
                <Boton style={{ flex: 1 }} variante="secondary" texto="📎 Archivo / PDF" onPress={() => subirActa(elegirDocumento)} disabled={subiendo} />
              </View>
            ) : null}
          </View>
        ) : null}

        <Boton variante="secondary" texto="📄  Ver / compartir hoja en PDF" onPress={() => Linking.openURL(hoja.pdf_url)} style={{ marginBottom: 12 }} />

        <Card titulo="Servicio">
          <Fila label="Tipo" valor={hoja.tipo_servicio_label} />
          <Fila label="Cliente" valor={hoja.proyecto.entidad} />
          <Fila label="Proyecto / servicio" valor={hoja.proyecto.nombre} />
          {hoja.incidencia ? (
            <Pressable onPress={() => router.push(`/incidencia/${hoja.incidencia!.id}`)}>
              <Fila label="Incidencia" valor={`${hoja.incidencia.codigo}  ›`} />
            </Pressable>
          ) : null}
          {hoja.mantenimiento ? <Fila label="Mantenimiento" valor={hoja.mantenimiento} /> : null}
          <Fila label="Fecha y horario" valor={`${fecha(hoja.fecha, false)} · ${hoja.hora_llegada}${hoja.hora_salida ? ` – ${hoja.hora_salida}` : ''}`} />
          {hoja.diagnostico ? <Fila label="Diagnóstico" valor={hoja.diagnostico} /> : null}
          <Fila label="Trabajo realizado" valor={hoja.trabajo_realizado} />
          {hoja.observaciones ? <Fila label="Observaciones" valor={hoja.observaciones} /> : null}
          {hoja.recomendaciones ? <Fila label="Recomendaciones" valor={hoja.recomendaciones} /> : null}
          {hoja.receptor_nombre ? <Fila label="Recibe el servicio" valor={[hoja.receptor_nombre, hoja.receptor_cargo].filter(Boolean).join(' · ')} /> : null}
        </Card>

        {hoja.mediciones?.length ? (
          <Card titulo="Mediciones">
            {hoja.mediciones.map((m, i) => (
              <View key={i} style={s.medicion}>
                <Text style={{ color: color.textMuted, flex: 1 }}>{m.parametro}</Text>
                <Text style={{ fontWeight: '700', color: color.text }}>{m.valor} {m.unidad}</Text>
              </View>
            ))}
          </Card>
        ) : null}

        {hoja.equipos?.length ? (
          <Card titulo={`Equipos (${hoja.equipos.length})`}>
            {hoja.equipos.map((e, i) => (
              <View key={i} style={{ paddingVertical: 8, borderTopWidth: i ? 1 : 0, borderTopColor: color.border }}>
                <Text style={{ fontWeight: '700', color: color.text }}>{e.equipo}</Text>
                <Text style={{ color: color.textMuted, fontSize: 13 }}>{[e.marca, e.modelo, e.serie && `S/N ${e.serie}`].filter(Boolean).join(' · ')}</Text>
                <Text style={{ color: color.textMuted, fontSize: 13 }}>{e.accion} → {e.estado_final}</Text>
              </View>
            ))}
          </Card>
        ) : null}

        {fotos.length ? (
          <Card titulo={`Fotos (${fotos.length})`}>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
              {fotos.map((a) => (
                <View key={a.id} style={{ width: 100 }}>
                  <Image source={{ uri: a.url, headers: authHeaders() }} style={{ width: 100, height: 100, borderRadius: 8 }} />
                  {a.descripcion ? <Text style={{ fontSize: 11, color: color.textMuted }} numberOfLines={2}>{a.descripcion}</Text> : null}
                </View>
              ))}
            </View>
          </Card>
        ) : null}

        {actas.length ? (
          <Card titulo="Acta de conformidad">
            {actas.map((a) => (
              <Text key={a.id} style={{ color: '#065f46', fontWeight: '600', marginBottom: 4 }}>📄 {a.nombre} · {fecha(a.fecha)}</Text>
            ))}
            {hoja.puede_subir_acta ? (
              <Boton variante="secondary" texto="＋ Otra página del acta" onPress={() => subirActa(tomarFoto)} cargando={subiendo} style={{ marginTop: 8 }} />
            ) : null}
          </Card>
        ) : null}
      </ScrollView>
    </>
  );
}

const s = StyleSheet.create({
  pendiente: { backgroundColor: '#fffbeb', borderWidth: 1, borderColor: '#fcd34d', borderRadius: 14, padding: 14, marginBottom: 12 },
  continua: { backgroundColor: '#eff6ff', borderWidth: 1, borderColor: '#bfdbfe', borderRadius: 14, padding: 12, marginBottom: 12 },
  medicion: { flexDirection: 'row', paddingVertical: 6, borderBottomWidth: 1, borderBottomColor: color.border },
});
