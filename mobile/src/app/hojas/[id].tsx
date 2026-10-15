import { Stack, useFocusEffect, useLocalSearchParams, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { Alert, Image, Linking, Pressable, RefreshControl, ScrollView, Switch, Text, View } from 'react-native';
import { CampoFirma, firmaJson, type Firma } from '@/components/FirmaPad';
import { Badge, Boton, Campo, Card, Cargando, Fila } from '@/components/ui';
import { api, authHeaders } from '@/lib/api';
import { color, colorEstado, fecha } from '@/lib/theme';
import type { HojaServicio } from '@/lib/types';

export default function DetalleHoja() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const [hoja, setHoja] = useState<HojaServicio | null>(null);
  const [cargando, setCargando] = useState(false);

  const [receptor, setReceptor] = useState('');
  const [dni, setDni] = useState('');
  const [cargo, setCargo] = useState('');
  const [conforme, setConforme] = useState(true);
  const [firmaReceptor, setFirmaReceptor] = useState<Firma | null>(null);
  const [firmaTecnico, setFirmaTecnico] = useState<Firma | null>(null);
  const [firmando, setFirmando] = useState(false);

  const cargar = useCallback(async () => {
    setCargando(true);
    try {
      const h = await api.hoja(id);
      setHoja(h);
      setReceptor((r) => r || h.receptor_nombre || '');
    } catch (e) {
      Alert.alert('Error', (e as Error).message);
    } finally {
      setCargando(false);
    }
  }, [id]);

  useFocusEffect(useCallback(() => { cargar(); }, [cargar]));

  if (!hoja) return <Cargando />;

  const firmar = async () => {
    if (!receptor.trim() || !firmaReceptor) return Alert.alert('Faltan datos', 'Indique el nombre y la firma del responsable.');
    setFirmando(true);
    try {
      const form = new FormData();
      form.append('receptor_nombre', receptor.trim());
      if (dni.trim()) form.append('receptor_dni', dni.trim());
      if (cargo.trim()) form.append('receptor_cargo', cargo.trim());
      form.append('conforme', conforme ? '1' : '0');
      form.append('firma_receptor', firmaJson(firmaReceptor));
      if (firmaTecnico) form.append('firma_tecnico', firmaJson(firmaTecnico));
      setHoja(await api.firmarHoja(hoja.id, form));
      Alert.alert('Hoja firmada', 'Se registró la conformidad de la entidad.');
    } catch (e) {
      Alert.alert('No se pudo firmar', (e as Error).message);
    } finally {
      setFirmando(false);
    }
  };

  return (
    <>
      <Stack.Screen options={{ title: hoja.numero }} />
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 48 }} refreshControl={<RefreshControl refreshing={cargando} onRefresh={cargar} />}>
        <View style={{ flexDirection: 'row', gap: 8, marginBottom: 12 }}>
          <Badge texto={hoja.estado_label} tono={colorEstado[hoja.estado]} />
          {hoja.estado === 'firmada' ? <Badge texto={hoja.conforme ? 'Conforme' : 'Con observaciones'} tono={hoja.conforme ? 'green' : 'amber'} /> : null}
        </View>

        <Boton variante="secondary" texto="📄  Ver / compartir PDF" onPress={() => Linking.openURL(hoja.pdf_url)} style={{ marginBottom: 12 }} />

        <Card titulo="Servicio">
          <Fila label="Tipo" valor={hoja.tipo_servicio_label} />
          <Fila label="Entidad" valor={hoja.proyecto.entidad} />
          <Fila label="Proyecto" valor={hoja.proyecto.nombre} />
          {hoja.incidencia ? (
            <Pressable onPress={() => router.push(`/incidencia/${hoja.incidencia!.id}`)}>
              <Fila label="Incidencia" valor={`${hoja.incidencia.codigo}  ›`} />
            </Pressable>
          ) : null}
          <Fila label="Fecha y horario" valor={`${fecha(hoja.fecha, false)} · ${hoja.hora_llegada}${hoja.hora_salida ? ` – ${hoja.hora_salida}` : ''}`} />
          {hoja.diagnostico ? <Fila label="Diagnóstico" valor={hoja.diagnostico} /> : null}
          <Fila label="Trabajo realizado" valor={hoja.trabajo_realizado} />
          {hoja.resultado_label ? <Fila label="Resultado" valor={hoja.resultado_label} /> : null}
          {hoja.observaciones ? <Fila label="Observaciones" valor={hoja.observaciones} /> : null}
          {hoja.recomendaciones ? <Fila label="Recomendaciones" valor={hoja.recomendaciones} /> : null}
        </Card>

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

        {hoja.archivos?.some((a) => a.es_imagen) ? (
          <Card titulo="Fotos">
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
              {hoja.archivos.filter((a) => a.es_imagen).map((a) => (
                <Image key={a.id} source={{ uri: a.url, headers: authHeaders() }} style={{ width: 92, height: 92, borderRadius: 8 }} />
              ))}
            </View>
          </Card>
        ) : null}

        {hoja.estado === 'firmada' ? (
          <Card titulo="Conformidad">
            <Fila label="Firmado por" valor={`${hoja.receptor_nombre}${hoja.receptor_cargo ? ` · ${hoja.receptor_cargo}` : ''}`} />
            <Fila label="Fecha de firma" valor={fecha(hoja.fecha_firma)} />
          </Card>
        ) : hoja.puede_firmar ? (
          <Card titulo="Firma de conformidad">
            <Campo label="Nombre de quien firma" value={receptor} onChangeText={setReceptor} requerido />
            <View style={{ flexDirection: 'row', gap: 10 }}>
              <View style={{ flex: 1 }}><Campo label="DNI" value={dni} onChangeText={setDni} keyboardType="number-pad" /></View>
              <View style={{ flex: 1 }}><Campo label="Cargo" value={cargo} onChangeText={setCargo} /></View>
            </View>
            <View style={{ flexDirection: 'row', alignItems: 'center', backgroundColor: '#ecfdf5', borderRadius: 10, padding: 12, marginBottom: 14 }}>
              <Text style={{ flex: 1, color: '#065f46', fontWeight: '600' }}>Da conformidad al servicio</Text>
              <Switch value={conforme} onValueChange={setConforme} />
            </View>
            <CampoFirma titulo="Firma del responsable" firma={firmaReceptor} onChange={setFirmaReceptor} />
            <CampoFirma titulo="Firma del técnico" firma={firmaTecnico} onChange={setFirmaTecnico} />
            <Boton variante="success" texto="Registrar firma" onPress={firmar} cargando={firmando} />
          </Card>
        ) : null}
      </ScrollView>
    </>
  );
}
