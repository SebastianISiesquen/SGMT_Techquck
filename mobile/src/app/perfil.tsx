import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { Pressable, ScrollView, Text, View } from 'react-native';
import { Boton, Card, Fila } from '@/components/ui';
import { api, getServidor } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { color, fecha } from '@/lib/theme';
import type { Notificacion } from '@/lib/types';

export default function Perfil() {
  const { usuario, salir, refrescar } = useAuth();
  const router = useRouter();
  const [notificaciones, setNotificaciones] = useState<Notificacion[]>([]);

  useFocusEffect(useCallback(() => {
    api.notificaciones().then(setNotificaciones).catch(() => {});
  }, []));

  const marcarLeidas = async () => {
    await api.leerNotificaciones();
    setNotificaciones((n) => n.map((x) => ({ ...x, leida: true })));
    refrescar().catch(() => {});
  };

  return (
    <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 48 }}>
      <Card>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 14, marginBottom: 14 }}>
          <View style={{ width: 54, height: 54, borderRadius: 27, backgroundColor: color.brand, alignItems: 'center', justifyContent: 'center' }}>
            <Text style={{ color: '#fff', fontWeight: '800', fontSize: 18 }}>{usuario?.iniciales}</Text>
          </View>
          <View style={{ flex: 1 }}>
            <Text style={{ fontSize: 18, fontWeight: '800', color: color.text }}>{usuario?.nombre}</Text>
            <Text style={{ color: color.textMuted }}>{usuario?.rol}{usuario?.cargo ? ` · ${usuario.cargo}` : ''}</Text>
          </View>
        </View>
        <Fila label="Correo" valor={usuario?.email} />
        <Fila label="Teléfono" valor={usuario?.telefono} />
        <Fila label="Servidor" valor={getServidor()} />
      </Card>

      <Card titulo="Notificaciones">
        {notificaciones.some((n) => !n.leida) ? (
          <Pressable onPress={marcarLeidas} style={{ alignSelf: 'flex-end', marginTop: -30, marginBottom: 10 }}>
            <Text style={{ color: color.brand, fontWeight: '600', fontSize: 13 }}>Marcar leídas</Text>
          </Pressable>
        ) : null}
        {notificaciones.length ? notificaciones.map((n) => (
          <Pressable key={n.id} onPress={() => n.incidencia_id && router.push(`/incidencia/${n.incidencia_id}`)}
                     style={{ paddingVertical: 10, borderTopWidth: 1, borderTopColor: color.border }}>
            <Text style={{ fontWeight: n.leida ? '500' : '800', color: color.text }}>{n.titulo}</Text>
            <Text style={{ color: color.textMuted, fontSize: 13 }}>{n.mensaje}</Text>
            <Text style={{ color: '#94a3b8', fontSize: 12, marginTop: 2 }}>{fecha(n.fecha)}</Text>
          </Pressable>
        )) : <Text style={{ color: color.textMuted }}>Sin notificaciones.</Text>}
      </Card>

      <Boton texto="Cerrar sesión" variante="danger" onPress={salir} />
    </ScrollView>
  );
}
