import { Stack, useFocusEffect, useLocalSearchParams, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { Alert, Image, Linking, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Badge, Boton, Card, Cargando, Fila } from '@/components/ui';
import { api, authHeaders } from '@/lib/api';
import { adjuntar, elegirDocumento, tomarFoto, type ArchivoLocal } from '@/lib/archivos';
import { color, colorEstado, colorPrioridad, fecha } from '@/lib/theme';
import type { Archivo, Incidencia } from '@/lib/types';

export default function DetalleIncidencia() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const [inc, setInc] = useState<Incidencia | null>(null);
  const [cargando, setCargando] = useState(false);
  const [accion, setAccion] = useState('');

  const cargar = useCallback(async () => {
    setCargando(true);
    try {
      setInc(await api.incidencia(id));
    } catch (e) {
      Alert.alert('Error', (e as Error).message);
    } finally {
      setCargando(false);
    }
  }, [id]);

  useFocusEffect(useCallback(() => { cargar(); }, [cargar]));

  if (!inc) return <Cargando />;

  const iniciar = async () => {
    setAccion('iniciar');
    try {
      await api.iniciar(inc.id);
      await cargar();
    } catch (e) {
      Alert.alert('Error', (e as Error).message);
    } finally {
      setAccion('');
    }
  };

  // Subida suelta: p. ej. el acta firmada que la entidad entrega después.
  const subir = async (fuente: () => Promise<ArchivoLocal[]>, categoria: string) => {
    const archivos = await fuente();
    if (!archivos.length) return;
    setAccion('subir');
    try {
      for (const a of archivos) {
        const form = new FormData();
        adjuntar(form, 'archivo', a);
        form.append('categoria', a.esImagen && categoria !== 'acta_conformidad' ? 'foto' : categoria);
        await api.subirArchivo(inc.id, form);
      }
      await cargar();
      Alert.alert('Listo', 'Archivo(s) subido(s) correctamente.');
    } catch (e) {
      Alert.alert('Error al subir', (e as Error).message);
    } finally {
      setAccion('');
    }
  };

  const puedeAdjuntar = ['asignada', 'en_proceso', 'resuelta'].includes(inc.estado);
  const evidencias = [...(inc.archivos ?? []), ...(inc.atenciones ?? []).flatMap((a) => a.archivos)];
  const tieneActa = evidencias.some((a) => a.categoria === 'acta_conformidad');
  const tieneHojaFirmada = (inc.hojas ?? []).some((h) => h.estado === 'conforme');
  const hojasSinActa = (inc.hojas ?? []).filter((h) => h.estado === 'registrada');

  return (
    <>
      <Stack.Screen options={{ title: inc.codigo }} />
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 48 }} refreshControl={<RefreshControl refreshing={cargando} onRefresh={cargar} />}>
        <View style={{ flexDirection: 'row', gap: 8, marginBottom: 8 }}>
          <Badge texto={inc.estado_label} tono={colorEstado[inc.estado]} />
          <Badge texto={`Prioridad ${inc.prioridad_label}`} tono={colorPrioridad[inc.prioridad]} />
          {inc.sla_vencido ? <Badge texto="Fuera de plazo" tono="red" /> : null}
        </View>
        <View style={{ marginBottom: 8 }}>
          <Badge texto={inc.cobertura_label} tono={inc.cobertura === 'garantia' ? 'green' : inc.cobertura === 'contrato' ? 'blue' : 'amber'} />
        </View>
        <Text style={s.titulo}>{inc.titulo}</Text>
        <Text style={s.descripcion}>{inc.descripcion}</Text>

        {inc.proxima_visita && ['asignada', 'en_proceso'].includes(inc.estado) ? (
          <View style={s.proxima}>
            <Text style={{ fontWeight: '800', color: '#1e3a8a' }}>🗓️ Próxima visita: {fecha(inc.proxima_visita, false)}</Text>
            {inc.motivo_reprogramacion ? <Text style={{ color: '#1e3a8a', marginTop: 2 }}>Pendiente: {inc.motivo_reprogramacion}</Text> : null}
          </View>
        ) : null}

        {hojasSinActa.length ? (
          <View style={s.sinActa}>
            <Text style={{ fontWeight: '700', color: '#92400e', marginBottom: 6 }}>Falta subir el acta firmada:</Text>
            {hojasSinActa.map((h) => (
              <Pressable key={h.id} onPress={() => router.push(`/hojas/${h.id}`)} style={{ paddingVertical: 4 }}>
                <Text style={{ color: color.brand, fontWeight: '700' }}>📄 {h.numero}  ›</Text>
              </Pressable>
            ))}
          </View>
        ) : null}

        {/* Acciones del técnico */}
        <View style={{ gap: 10, marginBottom: 16 }}>
          {inc.estado === 'asignada' && inc.puede_atender ? (
            <Boton texto="▶  Iniciar atención" onPress={iniciar} cargando={accion === 'iniciar'} variante="secondary" />
          ) : null}
          {inc.puede_atender ? (
            <Boton texto="📋  Registrar visita (hoja de servicio)"
                   onPress={() => router.push({ pathname: '/hojas/nueva', params: { incidencia_id: String(inc.id) } })} />
          ) : null}
          {puedeAdjuntar ? (
            <View style={{ flexDirection: 'row', gap: 10 }}>
              <Boton style={{ flex: 1 }} variante={tieneActa ? 'secondary' : 'success'} texto={tieneActa ? '📄 Otra acta' : '📄 Subir acta'}
                     onPress={() => subir(elegirDocumento, 'acta_conformidad')} cargando={accion === 'subir'} />
              <Boton style={{ flex: 1 }} variante="secondary" texto="📷 Foto" onPress={() => subir(tomarFoto, 'foto')} disabled={accion === 'subir'} />
            </View>
          ) : null}
          {inc.estado === 'resuelta' && !tieneHojaFirmada ? (
            <Boton variante="secondary" texto="📋  Hoja de servicio para el acta"
                   onPress={() => router.push({ pathname: '/hojas/nueva', params: { incidencia_id: String(inc.id) } })} />
          ) : null}
          {inc.estado === 'resuelta' && !tieneActa && !tieneHojaFirmada ? (
            <Text style={s.aviso}>Para cerrar, el supervisor necesita el acta de conformidad firmada por el cliente.</Text>
          ) : null}
        </View>

        {inc.equipo ? (
          <Card titulo="Equipo afectado">
            <Pressable onPress={() => router.push(`/equipos/${inc.equipo!.id}`)}>
              <Text style={{ fontWeight: '700', color: color.text }}>{inc.equipo.codigo} · {inc.equipo.nombre}</Text>
              <Text style={{ color: color.textMuted, fontSize: 13, marginTop: 2 }}>{[inc.equipo.ubicacion, inc.equipo.responsable].filter(Boolean).join(' · ') || ' '}</Text>
            </Pressable>
            {inc.equipo.antecedentes.length ? (
              <View style={s.antecedentes}>
                <Text style={{ fontWeight: '700', color: '#92400e', marginBottom: 6 }}>⚠️ Antecedentes del equipo</Text>
                {inc.equipo.antecedentes.map((a, i) => (
                  <Text key={i} style={{ color: '#92400e', fontSize: 13, marginBottom: 4 }}>
                    <Text style={{ fontWeight: '700' }}>{fecha(a.fecha, false)} · {a.tipo_label}: </Text>{a.titulo}
                  </Text>
                ))}
              </View>
            ) : <Text style={{ color: color.textMuted, fontSize: 13, marginTop: 8 }}>Sin antecedentes registrados.</Text>}
            <Pressable onPress={() => router.push(`/equipos/${inc.equipo!.id}`)} style={[s.llamar, { marginTop: 10, marginBottom: 0 }]}>
              <Text style={{ color: color.brand, fontWeight: '700' }}>Ver historial completo ›</Text>
            </Pressable>
          </Card>
        ) : null}

        <Card titulo="Lugar y contacto">
          <Fila label="Cliente" valor={inc.proyecto.entidad} />
          {inc.proyecto.contratante ? <Fila label="Contratado por" valor={inc.proyecto.contratante} /> : null}
          <Fila label="Proyecto" valor={`${inc.proyecto.codigo} · ${inc.proyecto.nombre}`} />
          <Fila label="Ubicación / equipo" valor={inc.ubicacion_detalle ?? inc.proyecto.ubicacion} />
          <Fila label="Reportado por" valor={inc.reportado_por ?? inc.proyecto.contacto} />
          {inc.proyecto.contacto_telefono ? (
            <Pressable onPress={() => Linking.openURL(`tel:${inc.proyecto.contacto_telefono}`)} style={s.llamar}>
              <Text style={{ color: color.brand, fontWeight: '700' }}>📞 Llamar {inc.proyecto.contacto_telefono}</Text>
            </Pressable>
          ) : null}
          <Fila label="Fecha de reporte" valor={fecha(inc.fecha_reporte)} />
          <Fila label="Fecha límite" valor={fecha(inc.fecha_limite)} />
        </Card>

        {inc.garantia ? (
          <Card titulo="Garantía">
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 10 }}>
              <Text style={{ fontWeight: '700', color: color.text, flex: 1 }}>{inc.garantia.tipo}</Text>
              <Badge texto={inc.garantia.estado_label} tono={colorEstado[inc.garantia.estado]} />
            </View>
            <Fila label="Vence" valor={fecha(inc.garantia.fecha_vencimiento, false)} />
            <Fila label="Cobertura" valor={inc.garantia.cobertura} />
            <Fila label="Condiciones" valor={inc.garantia.condiciones} />
          </Card>
        ) : null}

        <Card titulo={`Atenciones (${inc.atenciones?.length ?? 0})`}>
          {inc.atenciones?.length ? inc.atenciones.map((a) => (
            <View key={a.id} style={s.atencion}>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 8 }}>
                <Text style={{ color: color.textMuted, fontSize: 12 }}>{fecha(a.fecha_inicio)} · {a.tecnico}</Text>
                <Badge texto={a.resultado_label} tono={colorEstado[a.resultado]} />
              </View>
              <Fila label="Diagnóstico" valor={a.diagnostico} />
              <Fila label="Actividades" valor={a.actividades} />
              {a.observaciones ? <Fila label="Observaciones" valor={a.observaciones} /> : null}
              <Galeria archivos={a.archivos} />
            </View>
          )) : <Text style={{ color: color.textMuted }}>Aún no se registran atenciones.</Text>}
        </Card>

        {inc.archivos?.length ? (
          <Card titulo="Documentos de la incidencia"><Galeria archivos={inc.archivos} /></Card>
        ) : null}

        {inc.hojas?.length ? (
          <Card titulo="Hojas de servicio">
            {inc.hojas.map((h) => (
              <Pressable key={h.id} onPress={() => router.push(`/hojas/${h.id}`)} style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingVertical: 8 }}>
                <Text style={{ fontWeight: '600', color: color.text }}>{h.numero}  ›</Text>
                <Badge texto={h.estado_label} tono={h.estado === 'registrada' ? 'amber' : colorEstado[h.estado]} />
              </Pressable>
            ))}
          </Card>
        ) : null}

        <Card titulo="Historial">
          {inc.historial?.map((h, i) => (
            <View key={i} style={s.hist}>
              <View style={s.histPunto} />
              <View style={{ flex: 1 }}>
                <Text style={{ fontWeight: '600', color: color.text }}>{h.accion}{h.estado_nuevo ? ` → ${h.estado_nuevo}` : ''}</Text>
                {h.detalle ? <Text style={{ color: color.textMuted, fontSize: 13 }}>{h.detalle}</Text> : null}
                <Text style={{ color: '#94a3b8', fontSize: 12 }}>{fecha(h.fecha)} · {h.usuario ?? 'Sistema'}</Text>
              </View>
            </View>
          ))}
        </Card>
      </ScrollView>
    </>
  );
}

function Galeria({ archivos }: { archivos: Archivo[] }) {
  if (!archivos.length) return null;
  return (
    <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 4 }}>
      {archivos.map((f) => f.es_imagen ? (
        <Image key={f.id} source={{ uri: f.url, headers: authHeaders() }} style={s.foto} />
      ) : (
        <View key={f.id} style={s.docChip}>
          <Text style={{ fontSize: 12, fontWeight: '700', color: f.categoria === 'acta_conformidad' ? color.success : color.text }}>{f.categoria_label}</Text>
          <Text style={{ fontSize: 11, color: color.textMuted }} numberOfLines={1}>{f.nombre}</Text>
        </View>
      ))}
    </View>
  );
}

const s = StyleSheet.create({
  titulo: { fontSize: 20, fontWeight: '800', color: color.text, marginBottom: 6 },
  descripcion: { fontSize: 15, color: '#334155', lineHeight: 21, marginBottom: 16 },
  antecedentes: { backgroundColor: '#fffbeb', borderRadius: 10, padding: 10, marginTop: 10 },
  proxima: { backgroundColor: '#eff6ff', borderWidth: 1, borderColor: '#bfdbfe', borderRadius: 12, padding: 12, marginBottom: 12 },
  sinActa: { backgroundColor: '#fffbeb', borderWidth: 1, borderColor: '#fcd34d', borderRadius: 12, padding: 12, marginBottom: 12 },
  aviso: { backgroundColor: '#fffbeb', color: '#92400e', padding: 10, borderRadius: 8, fontSize: 13 },
  llamar: { backgroundColor: color.brandSoft, padding: 10, borderRadius: 8, marginBottom: 10, alignItems: 'center' },
  atencion: { borderTopWidth: 1, borderTopColor: color.border, paddingTop: 12, marginTop: 4 },
  foto: { width: 92, height: 92, borderRadius: 8, backgroundColor: color.bg },
  docChip: { width: 140, borderWidth: 1, borderColor: color.border, borderRadius: 8, padding: 8 },
  hist: { flexDirection: 'row', gap: 10, marginBottom: 12 },
  histPunto: { width: 10, height: 10, borderRadius: 5, backgroundColor: color.brand, marginTop: 5 },
});
