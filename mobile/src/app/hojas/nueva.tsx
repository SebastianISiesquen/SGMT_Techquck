import * as Location from 'expo-location';
import { useFocusEffect, useLocalSearchParams, useRouter } from 'expo-router';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Alert, Image, KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Switch, Text, TextInput, View } from 'react-native';
import { CampoFirma, firmaJson, type Firma } from '@/components/FirmaPad';
import { Boton, Campo, Card, Cargando, Chip } from '@/components/ui';
import { api } from '@/lib/api';
import { adjuntar, elegirDeGaleria, tomarFoto, type ArchivoLocal } from '@/lib/archivos';
import { tomarEquipoEscaneado } from '@/lib/seleccion';
import { color } from '@/lib/theme';
import type { ArticuloAlmacen, Equipo as EquipoInventario, Incidencia, ProyectoOpcion, PuntoChecklist } from '@/lib/types';

const TIPOS = [
  { valor: 'correctivo', texto: 'Correctivo' },
  { valor: 'preventivo', texto: 'Preventivo' },
  { valor: 'instalacion', texto: 'Instalación' },
  { valor: 'inspeccion', texto: 'Inspección' },
];
const ACCIONES = [
  { valor: 'revisado', texto: 'Revisado' }, { valor: 'configurado', texto: 'Configurado' }, { valor: 'reparado', texto: 'Reparado' },
  { valor: 'cambio_componente', texto: 'Cambio de componente' }, { valor: 'reemplazado', texto: 'Reemplazado' },
  { valor: 'instalado', texto: 'Instalado' }, { valor: 'retirado', texto: 'Retirado' },
];
const ESTADOS = [{ valor: 'operativo', texto: 'Operativo' }, { valor: 'observado', texto: 'Con observ.' }, { valor: 'inoperativo', texto: 'Inoperativo' }];
const RESULTADOS = [
  { valor: 'resuelto', texto: 'Resuelto' }, { valor: 'parcial', texto: 'Atención parcial' },
  { valor: 'pendiente_repuesto', texto: 'Pendiente de repuesto' }, { valor: 'no_procede', texto: 'No procede' },
];
const PUNTOS = [
  { valor: 'ok', texto: 'OK', fondo: '#059669' }, { valor: 'observado', texto: 'Obs.', fondo: '#d97706' },
  { valor: 'falla', texto: 'Falla', fondo: '#dc2626' }, { valor: 'na', texto: 'N/A', fondo: '#64748b' },
];
const clave = (t: string) => t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toLowerCase();

interface Equipo { equipo_id: string; codigo: string; equipo: string; marca: string; modelo: string; serie: string; accion: string; estado_final: string; observacion: string; checklist: PuntoChecklist[] }
const equipoVacio: Equipo = { equipo_id: '', codigo: '', equipo: '', marca: '', modelo: '', serie: '', accion: 'revisado', estado_final: 'operativo', observacion: '', checklist: [] };
const desdeInventario = (e: EquipoInventario, accion = 'revisado'): Equipo => ({
  ...equipoVacio, equipo_id: String(e.id), codigo: e.codigo, equipo: e.categoria, marca: e.marca ?? '', modelo: e.modelo ?? '', serie: e.serie ?? '', accion,
});
interface Material { articulo_id: string; descripcion: string; cantidad: string; unidad: string }

const dos = (n: number) => String(n).padStart(2, '0');
const horaDe = (d: Date) => `${dos(d.getHours())}:${dos(d.getMinutes())}`;
const hoy = () => { const d = new Date(); return `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())}`; };

/** Hoja de servicio en campo: datos, equipos, materiales, fotos y firma del responsable (con el dedo). */
export default function NuevaHoja() {
  const { incidencia_id, mantenimiento_id, proyecto_id, etiqueta } = useLocalSearchParams<{ incidencia_id?: string; mantenimiento_id?: string; proyecto_id?: string; etiqueta?: string }>();
  const router = useRouter();

  const [listo, setListo] = useState(false);
  const [incidencia, setIncidencia] = useState<Incidencia | null>(null);
  const [proyectos, setProyectos] = useState<ProyectoOpcion[]>([]);
  const [buscar, setBuscar] = useState('');
  const [proyectoId, setProyectoId] = useState<number | null>(proyecto_id ? Number(proyecto_id) : null);
  const [inventario, setInventario] = useState<EquipoInventario[]>([]);
  const [verInventario, setVerInventario] = useState(false);
  const [garantiaId, setGarantiaId] = useState<number | null>(null);

  const [tipo, setTipo] = useState(incidencia_id ? 'correctivo' : 'preventivo');
  const [llegada, setLlegada] = useState(horaDe(new Date(Date.now() - 3600_000)));
  const [salida, setSalida] = useState(horaDe(new Date()));
  const [diagnostico, setDiagnostico] = useState('');
  const [resultado, setResultado] = useState('resuelto');
  const [articulos, setArticulos] = useState<ArticuloAlmacen[]>([]);
  const [verAlmacen, setVerAlmacen] = useState(false);
  const [checklists, setChecklists] = useState<Record<string, string[]>>({});
  const [trabajo, setTrabajo] = useState('');
  const [observaciones, setObservaciones] = useState('');
  const [recomendaciones, setRecomendaciones] = useState('');
  const [equipos, setEquipos] = useState<Equipo[]>([]);
  const [materiales, setMateriales] = useState<Material[]>([]);
  const [fotos, setFotos] = useState<ArchivoLocal[]>([]);

  const [receptor, setReceptor] = useState('');
  const [dni, setDni] = useState('');
  const [cargo, setCargo] = useState('');
  const [conforme, setConforme] = useState(true);
  const [firmaReceptor, setFirmaReceptor] = useState<Firma | null>(null);
  const [firmaTecnico, setFirmaTecnico] = useState<Firma | null>(null);
  const [enviando, setEnviando] = useState(false);

  useEffect(() => {
    (async () => {
      try {
        const [lista, inc, arts, listas] = await Promise.all([
          api.proyectos(),
          incidencia_id ? api.incidencia(incidencia_id) : Promise.resolve(null),
          api.almacen().catch(() => [] as ArticuloAlmacen[]),
          api.checklists().catch(() => ({} as Record<string, string[]>)),
        ]);
        setProyectos(lista);
        setArticulos(arts);
        setChecklists(listas);
        if (inc) {
          setIncidencia(inc);
          setProyectoId(inc.proyecto.id);
          setReceptor(inc.reportado_por ?? '');
          // El equipo de la incidencia ya aparece en la lista de equipos intervenidos
          if (inc.equipo) {
            setEquipos([{ ...equipoVacio, equipo_id: String(inc.equipo.id), codigo: inc.equipo.codigo, equipo: inc.equipo.categoria, accion: 'reparado',
              checklist: (listas[clave(inc.equipo.categoria)] ?? []).map((item) => ({ item, resultado: '', nota: '' })) }]);
          }
          const ultima = inc.atenciones?.[0];
          if (ultima) {
            setDiagnostico(ultima.diagnostico);
            setTrabajo(ultima.actividades);
            setObservaciones(ultima.observaciones ?? '');
          }
        }
      } catch (e) {
        Alert.alert('Error', (e as Error).message);
      } finally {
        setListo(true);
      }
    })();
  }, [incidencia_id]);

  // Inventario del cliente del proyecto elegido
  useEffect(() => {
    setInventario([]);
    if (proyectoId) api.equiposDeProyecto(proyectoId).then(setInventario).catch(() => setInventario([]));
  }, [proyectoId]);

  // Al volver del escáner se agrega el equipo leído
  useFocusEffect(useCallback(() => {
    const leido = tomarEquipoEscaneado();
    if (leido) agregarDelInventario(leido);
  }, []));

  const puntosPara = (categoria: string): PuntoChecklist[] => (checklists[clave(categoria)] ?? []).map((item) => ({ item, resultado: '', nota: '' }));

  const agregarDelInventario = (e: EquipoInventario) => {
    setEquipos((l) => (l.some((x) => x.equipo_id === String(e.id)) ? l
      : [...l, { ...desdeInventario(e, tipo === 'preventivo' ? 'revisado' : 'reparado'), checklist: puntosPara(e.categoria) }]));
    setVerInventario(false);
  };

  const agregarDelAlmacen = (a: ArticuloAlmacen) => {
    setMateriales((l) => [...l, { articulo_id: String(a.id), descripcion: a.nombre, cantidad: '1', unidad: a.unidad }]);
    setVerAlmacen(false);
  };

  const proyecto = useMemo(() => proyectos.find((p) => p.id === proyectoId) ?? null, [proyectos, proyectoId]);
  const filtrados = proyectos.filter((p) => `${p.nombre} ${p.entidad} ${p.codigo}`.toLowerCase().includes(buscar.toLowerCase())).slice(0, 8);

  if (!listo) return <Cargando />;

  const agregarFotos = async (fuente: () => Promise<ArchivoLocal[]>) => {
    const nuevas = await fuente();
    if (nuevas.length) setFotos((f) => [...f, ...nuevas]);
  };

  const guardar = async () => {
    if (!proyectoId) return Alert.alert('Falta el proyecto', 'Seleccione el proyecto atendido.');
    if (!/^\d{1,2}:\d{2}$/.test(llegada) || (salida && !/^\d{1,2}:\d{2}$/.test(salida))) return Alert.alert('Hora inválida', 'Use el formato HH:MM.');
    if (incidencia && !diagnostico.trim()) return Alert.alert('Falta el diagnóstico', 'Indique qué encontró y la causa de la falla.');
    if (!trabajo.trim()) return Alert.alert('Faltan datos', 'Describa el trabajo realizado.');
    const sinStock = materiales.find((m) => m.articulo_id && Number(m.cantidad) > (articulos.find((a) => String(a.id) === m.articulo_id)?.stock ?? 0));
    if (sinStock) return Alert.alert('Stock insuficiente', `No hay suficiente «${sinStock.descripcion}» en el almacén.`);
    if (firmaReceptor && !receptor.trim()) return Alert.alert('Falta el nombre', 'Indique el nombre de quien firma por el cliente.');

    setEnviando(true);
    try {
      const form = new FormData();
      if (incidencia) form.append('incidencia_id', String(incidencia.id));
      if (mantenimiento_id) form.append('mantenimiento_id', String(mantenimiento_id));
      form.append('proyecto_id', String(proyectoId));
      if (garantiaId && !incidencia) form.append('garantia_id', String(garantiaId));
      form.append('tipo_servicio', tipo);
      form.append('fecha', hoy());
      form.append('hora_llegada', llegada.padStart(5, '0'));
      if (salida) form.append('hora_salida', salida.padStart(5, '0'));
      if (incidencia) {
        form.append('diagnostico', diagnostico.trim());
        form.append('resultado', resultado);
      }
      form.append('trabajo_realizado', trabajo.trim());
      if (observaciones.trim()) form.append('observaciones', observaciones.trim());
      if (recomendaciones.trim()) form.append('recomendaciones', recomendaciones.trim());

      equipos.filter((e) => e.equipo.trim() || e.equipo_id).forEach((e, i) => {
        const { checklist, codigo, ...campos } = e;
        Object.entries(campos).filter(([, v]) => v !== '').forEach(([k, v]) => form.append(`equipos[${i}][${k}]`, v));
        checklist.filter((p) => p.resultado).forEach((p, j) => {
          form.append(`equipos[${i}][checklist][${j}][item]`, p.item);
          form.append(`equipos[${i}][checklist][${j}][resultado]`, p.resultado);
          if (p.nota.trim()) form.append(`equipos[${i}][checklist][${j}][nota]`, p.nota.trim());
        });
      });
      materiales.filter((m) => m.descripcion.trim()).forEach((m, i) => {
        Object.entries(m).filter(([, v]) => v !== '').forEach(([k, v]) => form.append(`materiales[${i}][${k}]`, v));
      });
      fotos.forEach((f, i) => adjuntar(form, `fotos[${i}]`, f));

      if (firmaReceptor) {
        form.append('receptor_nombre', receptor.trim());
        if (dni.trim()) form.append('receptor_dni', dni.trim());
        if (cargo.trim()) form.append('receptor_cargo', cargo.trim());
        form.append('conforme', conforme ? '1' : '0');
        form.append('firma_receptor', firmaJson(firmaReceptor));
        if (firmaTecnico) form.append('firma_tecnico', firmaJson(firmaTecnico));
      }

      const { granted } = await Location.requestForegroundPermissionsAsync().catch(() => ({ granted: false }));
      if (granted) {
        const pos = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced }).catch(() => null);
        if (pos) {
          form.append('latitud', String(pos.coords.latitude));
          form.append('longitud', String(pos.coords.longitude));
        }
      }

      const hoja = await api.crearHoja(form);
      Alert.alert(`Hoja ${hoja.numero}`, hoja.estado === 'firmada' ? 'Registrada y firmada.' : 'Registrada. Queda pendiente la firma del cliente.');
      router.replace(`/hojas/${hoja.id}`);
    } catch (e) {
      Alert.alert('No se pudo guardar', (e as Error).message);
    } finally {
      setEnviando(false);
    }
  };

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 48 }} keyboardShouldPersistTaps="handled">
        {mantenimiento_id ? (
          <View style={s.mantenimiento}>
            <Text style={{ color: '#1e3a8a', fontWeight: '700' }}>🗓️ {etiqueta ?? 'Mantenimiento programado'}</Text>
            <Text style={{ color: '#1e3a8a', fontSize: 13 }}>Al guardar, la visita queda como realizada.</Text>
          </View>
        ) : null}
        <Card titulo="1. Proyecto atendido">
          {incidencia ? (
            <View style={{ marginBottom: 12 }}>
              <Text style={s.fuerte}>{incidencia.codigo} · {incidencia.titulo}</Text>
              <Text style={s.tenue}>{incidencia.proyecto.entidad}</Text>
            </View>
          ) : proyecto ? (
            <Pressable onPress={() => { if (!mantenimiento_id) { setProyectoId(null); setGarantiaId(null); } }} style={s.seleccionado}>
              <View style={{ flex: 1 }}>
                <Text style={s.fuerte}>{proyecto.nombre}</Text>
                <Text style={s.tenue}>{proyecto.entidad}</Text>
              </View>
              {!mantenimiento_id ? <Text style={{ color: color.brand, fontWeight: '700' }}>Cambiar</Text> : null}
            </Pressable>
          ) : (
            <View>
              <TextInput value={buscar} onChangeText={setBuscar} placeholder="Buscar proyecto o cliente…" placeholderTextColor="#94a3b8" style={s.input} />
              {filtrados.map((p) => (
                <Pressable key={p.id} onPress={() => setProyectoId(p.id)} style={s.opcion}>
                  <Text style={s.fuerte}>{p.nombre}</Text>
                  <Text style={s.tenue}>{p.codigo} · {p.entidad}</Text>
                </Pressable>
              ))}
            </View>
          )}

          {!incidencia && proyecto?.garantias.length ? (
            <>
              <Text style={s.label}>Garantía</Text>
              <View style={s.chips}>
                {proyecto.garantias.map((g) => <Chip key={g.id} texto={g.texto} activo={garantiaId === g.id} onPress={() => setGarantiaId(garantiaId === g.id ? null : g.id)} />)}
              </View>
            </>
          ) : null}

          <Text style={s.label}>Tipo de servicio</Text>
          <View style={s.chips}>{TIPOS.map((t) => <Chip key={t.valor} texto={t.texto} activo={tipo === t.valor} onPress={() => setTipo(t.valor)} />)}</View>

          <View style={{ flexDirection: 'row', gap: 10 }}>
            <View style={{ flex: 1 }}><Campo label="Llegada" value={llegada} onChangeText={setLlegada} placeholder="HH:MM" keyboardType="numbers-and-punctuation" requerido /></View>
            <View style={{ flex: 1 }}><Campo label="Salida" value={salida} onChangeText={setSalida} placeholder="HH:MM" keyboardType="numbers-and-punctuation" /></View>
          </View>
        </Card>

        <Card titulo={`2. Equipos intervenidos (${equipos.length})`}>
          {equipos.map((e, i) => {
            const cambiar = (c: Partial<Equipo>) => setEquipos((l) => l.map((x, j) => (j === i ? { ...x, ...c } : x)));
            return (
              <View key={i} style={[s.bloque, e.equipo_id ? { borderColor: '#bfdbfe', backgroundColor: '#f8fbff' } : null]}>
                <View style={{ flexDirection: 'row', gap: 8, alignItems: 'center' }}>
                  {e.equipo_id ? (
                    <View style={{ flex: 1, marginBottom: 8 }}>
                      <Text style={{ fontSize: 12, fontWeight: '700', color: color.brand }}>{e.codigo} · del inventario</Text>
                      <Text style={s.fuerte}>{[e.equipo, e.marca, e.modelo].filter(Boolean).join(' ')}</Text>
                      {e.serie ? <Text style={s.tenue}>S/N {e.serie}</Text> : null}
                    </View>
                  ) : (
                    <TextInput value={e.equipo} onChangeText={(t) => cambiar({ equipo: t })} onEndEditing={() => cambiar({ checklist: puntosPara(e.equipo) })}
                               placeholder="Equipo (ej. Cámara PTZ)" placeholderTextColor="#94a3b8" style={[s.input, { flex: 1 }]} />
                  )}
                  <Pressable onPress={() => setEquipos((l) => l.filter((_, j) => j !== i))} hitSlop={8} style={{ justifyContent: 'center' }}>
                    <Text style={{ color: color.danger, fontWeight: '700' }}>✕</Text>
                  </Pressable>
                </View>
                {!e.equipo_id ? (
                  <>
                    <View style={{ flexDirection: 'row', gap: 8 }}>
                      <TextInput value={e.marca} onChangeText={(t) => cambiar({ marca: t })} placeholder="Marca" placeholderTextColor="#94a3b8" style={[s.input, { flex: 1 }]} />
                      <TextInput value={e.modelo} onChangeText={(t) => cambiar({ modelo: t })} placeholder="Modelo" placeholderTextColor="#94a3b8" style={[s.input, { flex: 1 }]} />
                    </View>
                    <TextInput value={e.serie} onChangeText={(t) => cambiar({ serie: t })} placeholder="N° de serie (lo registra en el inventario)" autoCapitalize="characters" placeholderTextColor="#94a3b8" style={s.input} />
                  </>
                ) : null}
                <View style={s.chips}>{ACCIONES.map((a) => <Chip key={a.valor} texto={a.texto} activo={e.accion === a.valor} onPress={() => cambiar({ accion: a.valor })} />)}</View>
                <View style={s.chips}>{ESTADOS.map((a) => <Chip key={a.valor} texto={a.texto} activo={e.estado_final === a.valor} onPress={() => cambiar({ estado_final: a.valor })} />)}</View>
                <TextInput value={e.observacion} onChangeText={(t) => cambiar({ observacion: t })}
                           placeholder={e.accion === 'cambio_componente' ? 'Componente cambiado (ej. disco duro)' : 'Observación'} placeholderTextColor="#94a3b8" style={s.input} />
                {e.checklist.length ? (
                  <View style={s.checklist}>
                    <Text style={[s.label, { marginBottom: 6 }]}>Checklist de mantenimiento</Text>
                    {e.checklist.map((p, j) => {
                      const marcar = (c: Partial<PuntoChecklist>) => cambiar({ checklist: e.checklist.map((x, k) => (k === j ? { ...x, ...c } : x)) });
                      return (
                        <View key={p.item} style={s.punto}>
                          <Text style={{ color: color.text, fontSize: 13, marginBottom: 6 }}>{p.item}</Text>
                          <View style={{ flexDirection: 'row', gap: 6 }}>
                            {PUNTOS.map((o) => (
                              <Pressable key={o.valor} onPress={() => marcar({ resultado: o.valor })}
                                         style={[s.puntoBtn, p.resultado === o.valor && { backgroundColor: o.fondo, borderColor: o.fondo }]}>
                                <Text style={{ fontSize: 12, fontWeight: '700', color: p.resultado === o.valor ? '#fff' : color.textMuted }}>{o.texto}</Text>
                              </Pressable>
                            ))}
                          </View>
                          {p.resultado === 'observado' || p.resultado === 'falla' ? (
                            <TextInput value={p.nota} onChangeText={(t) => marcar({ nota: t })} placeholder="¿Qué se encontró?" placeholderTextColor="#94a3b8" style={[s.input, { marginTop: 6, marginBottom: 0 }]} />
                          ) : null}
                        </View>
                      );
                    })}
                    <Pressable onPress={() => cambiar({ checklist: e.checklist.map((x) => (x.resultado ? x : { ...x, resultado: 'ok' })) })}>
                      <Text style={{ color: color.brand, fontWeight: '700', fontSize: 13, marginTop: 6 }}>Marcar pendientes como OK</Text>
                    </Pressable>
                  </View>
                ) : null}
              </View>
            );
          })}

          {verInventario ? (
            <View style={s.inventario}>
              {inventario.filter((x) => !equipos.some((e) => e.equipo_id === String(x.id))).map((x) => (
                <Pressable key={x.id} onPress={() => agregarDelInventario(x)} style={s.opcion}>
                  <Text style={s.fuerte}>{x.codigo} · {x.nombre}</Text>
                  {x.ubicacion ? <Text style={s.tenue}>{x.ubicacion}</Text> : null}
                </Pressable>
              ))}
              {!inventario.length ? <Text style={s.tenue}>Este cliente aún no tiene equipos registrados.</Text> : null}
            </View>
          ) : null}

          <View style={{ flexDirection: 'row', gap: 8 }}>
            <Boton style={{ flex: 1 }} variante="secondary" texto="📷 Escanear" onPress={() => router.push({ pathname: '/equipos/escanear', params: { para: 'hoja' } })} />
            <Boton style={{ flex: 1 }} variante="secondary" texto={verInventario ? 'Cerrar lista' : `Inventario (${inventario.length})`}
                   onPress={() => setVerInventario((v) => !v)} disabled={!proyectoId} />
          </View>
          <Boton variante="secondary" texto="＋ Equipo nuevo" style={{ marginTop: 8 }} onPress={() => setEquipos((l) => [...l, { ...equipoVacio }])} />
        </Card>

        <Card titulo={incidencia ? '3. Diagnóstico y trabajo realizado' : '3. Trabajo realizado'}>
          {incidencia ? <Campo label="Diagnóstico" value={diagnostico} onChangeText={setDiagnostico} multiline placeholder="¿Qué encontró? ¿Cuál es la causa?" requerido /> : null}
          <Campo label="Trabajo realizado" value={trabajo} onChangeText={setTrabajo} multiline requerido />
          {incidencia ? (
            <>
              <Text style={s.label}>Resultado *</Text>
              <View style={s.chips}>{RESULTADOS.map((r) => <Chip key={r.valor} texto={r.texto} activo={resultado === r.valor} onPress={() => setResultado(r.valor)} />)}</View>
            </>
          ) : null}
          <Campo label="Observaciones" value={observaciones} onChangeText={setObservaciones} multiline />
          <Campo label="Recomendaciones al cliente" value={recomendaciones} onChangeText={setRecomendaciones} multiline />

          <Text style={s.label}>Materiales / repuestos</Text>
          {materiales.map((m, i) => {
            const cambiar = (c: Partial<Material>) => setMateriales((l) => l.map((x, j) => (j === i ? { ...x, ...c } : x)));
            return (
              <View key={i} style={{ flexDirection: 'row', gap: 6, marginBottom: 8 }}>
                <TextInput value={m.descripcion} onChangeText={(t) => cambiar({ descripcion: t })} editable={!m.articulo_id} placeholder="Descripción" placeholderTextColor="#94a3b8"
                           style={[s.input, { flex: 1, marginBottom: 0 }, m.articulo_id ? { backgroundColor: color.brandSoft } : null]} />
                <TextInput value={m.cantidad} onChangeText={(t) => cambiar({ cantidad: t })} keyboardType="decimal-pad" style={[s.input, { width: 56, marginBottom: 0 }]} />
                <TextInput value={m.unidad} onChangeText={(t) => cambiar({ unidad: t })} style={[s.input, { width: 60, marginBottom: 0 }]} />
                <Pressable onPress={() => setMateriales((l) => l.filter((_, j) => j !== i))} hitSlop={8} style={{ justifyContent: 'center' }}>
                  <Text style={{ color: color.danger, fontWeight: '700' }}>✕</Text>
                </Pressable>
              </View>
            );
          })}
          {verAlmacen ? (
            <View style={s.inventario}>
              {articulos.map((a) => (
                <Pressable key={a.id} onPress={() => agregarDelAlmacen(a)} style={s.opcion}>
                  <Text style={s.fuerte}>{a.nombre}</Text>
                  <Text style={[s.tenue, a.stock <= 0 && { color: color.danger }]}>{a.codigo} · stock {a.stock} {a.unidad}</Text>
                </Pressable>
              ))}
              {!articulos.length ? <Text style={s.tenue}>No hay artículos en el almacén.</Text> : null}
            </View>
          ) : null}
          <View style={{ flexDirection: 'row', gap: 8 }}>
            <Boton style={{ flex: 1 }} variante="secondary" texto={verAlmacen ? 'Cerrar almacén' : '📦 Del almacén'} onPress={() => setVerAlmacen((v) => !v)} />
            <Boton style={{ flex: 1 }} variante="secondary" texto="＋ Otro material" onPress={() => setMateriales((l) => [...l, { articulo_id: '', descripcion: '', cantidad: '1', unidad: 'und' }])} />
          </View>
          {materiales.some((m) => m.articulo_id) ? <Text style={[s.tenue, { marginTop: 6 }]}>Los materiales del almacén se descuentan de su stock al guardar.</Text> : null}

          <Text style={[s.label, { marginTop: 16 }]}>Fotos ({fotos.length})</Text>
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 10 }}>
            {fotos.map((f, i) => (
              <Pressable key={f.uri} onLongPress={() => setFotos((l) => l.filter((_, j) => j !== i))}>
                <Image source={{ uri: f.uri }} style={s.foto} />
              </Pressable>
            ))}
          </View>
          <View style={{ flexDirection: 'row', gap: 10 }}>
            <Boton style={{ flex: 1 }} variante="secondary" texto="📷 Cámara" onPress={() => agregarFotos(tomarFoto)} />
            <Boton style={{ flex: 1 }} variante="secondary" texto="🖼️ Galería" onPress={() => agregarFotos(elegirDeGaleria)} />
          </View>
          {fotos.length ? <Text style={[s.tenue, { marginTop: 6 }]}>Mantenga presionada una foto para quitarla.</Text> : null}
        </Card>

        <Card titulo="4. Conformidad y firmas">
          <Text style={[s.tenue, { marginBottom: 12 }]}>Si el responsable no puede firmar ahora, deje la firma en blanco y fírmela después desde la hoja.</Text>
          <Campo label="Nombre de quien recibe" value={receptor} onChangeText={setReceptor} />
          <View style={{ flexDirection: 'row', gap: 10 }}>
            <View style={{ flex: 1 }}><Campo label="DNI" value={dni} onChangeText={setDni} keyboardType="number-pad" maxLength={15} /></View>
            <View style={{ flex: 1 }}><Campo label="Cargo" value={cargo} onChangeText={setCargo} /></View>
          </View>
          <View style={s.conforme}>
            <Text style={{ flex: 1, color: '#065f46', fontWeight: '600' }}>El cliente da conformidad al servicio</Text>
            <Switch value={conforme} onValueChange={setConforme} />
          </View>
          <CampoFirma titulo="Firma del responsable del cliente" firma={firmaReceptor} onChange={setFirmaReceptor} />
          <CampoFirma titulo="Firma del técnico" firma={firmaTecnico} onChange={setFirmaTecnico} />
        </Card>

        <Boton texto={firmaReceptor ? 'Guardar hoja firmada' : 'Guardar (pendiente de firma)'} onPress={guardar} cargando={enviando} />
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const s = StyleSheet.create({
  fuerte: { fontSize: 15, fontWeight: '700', color: color.text },
  tenue: { fontSize: 13, color: color.textMuted },
  label: { fontSize: 14, fontWeight: '600', color: color.text, marginBottom: 8 },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginBottom: 12 },
  input: { backgroundColor: '#fff', borderWidth: 1, borderColor: '#cbd5e1', borderRadius: 10, paddingHorizontal: 12, paddingVertical: 10, fontSize: 14, color: color.text, marginBottom: 8 },
  opcion: { paddingVertical: 10, borderTopWidth: 1, borderTopColor: color.border },
  seleccionado: { flexDirection: 'row', alignItems: 'center', gap: 10, backgroundColor: color.brandSoft, padding: 12, borderRadius: 10, marginBottom: 12 },
  checklist: { backgroundColor: '#f8fafc', borderRadius: 10, padding: 10, marginBottom: 8 },
  punto: { paddingVertical: 8, borderTopWidth: 1, borderTopColor: color.border },
  puntoBtn: { flex: 1, borderWidth: 1, borderColor: color.border, borderRadius: 8, paddingVertical: 7, alignItems: 'center', backgroundColor: '#fff' },
  mantenimiento: { backgroundColor: '#eff6ff', borderWidth: 1, borderColor: '#bfdbfe', borderRadius: 12, padding: 12, marginBottom: 12 },
  inventario: { borderWidth: 1, borderColor: color.border, borderRadius: 12, paddingHorizontal: 10, marginBottom: 10, maxHeight: 320 },
  bloque: { borderWidth: 1, borderColor: color.border, borderRadius: 12, padding: 10, marginBottom: 10 },
  foto: { width: 72, height: 72, borderRadius: 8 },
  conforme: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#ecfdf5', borderRadius: 10, padding: 12, marginBottom: 14 },
});
