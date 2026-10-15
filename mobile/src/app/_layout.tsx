import { Stack, useRouter, useSegments } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';
import { Cargando } from '@/components/ui';
import { AuthProvider, useAuth } from '@/lib/auth';
import { color } from '@/lib/theme';

function Navegacion() {
  const { usuario, cargando } = useAuth();
  const segments = useSegments();
  const router = useRouter();

  // Sin sesión → login; con sesión → bandeja.
  useEffect(() => {
    if (cargando) return;
    const enLogin = segments[0] === 'login';
    if (!usuario && !enLogin) router.replace('/login');
    else if (usuario && enLogin) router.replace('/');
  }, [usuario, cargando, segments]);

  if (cargando) return <Cargando />;

  return (
    <Stack
      screenOptions={{
        headerStyle: { backgroundColor: color.brandDark },
        headerTintColor: '#fff',
        headerTitleStyle: { fontWeight: '700' },
        contentStyle: { backgroundColor: color.bg },
        headerBackTitle: 'Atrás',
      }}
    >
      <Stack.Screen name="login" options={{ headerShown: false }} />
      <Stack.Screen name="index" options={{ title: 'Mis incidencias' }} />
      <Stack.Screen name="incidencia/[id]/index" options={{ title: 'Incidencia' }} />
      <Stack.Screen name="perfil" options={{ title: 'Mi cuenta' }} />
      <Stack.Screen name="hojas/index" options={{ title: 'Hojas de servicio' }} />
      <Stack.Screen name="hojas/nueva" options={{ title: 'Nueva hoja de servicio' }} />
      <Stack.Screen name="hojas/[id]" options={{ title: 'Hoja de servicio' }} />
      <Stack.Screen name="equipos/index" options={{ title: 'Equipos' }} />
      <Stack.Screen name="equipos/escanear" options={{ title: 'Escanear equipo' }} />
      <Stack.Screen name="equipos/[id]" options={{ title: 'Equipo' }} />
      <Stack.Screen name="mantenimientos" options={{ title: 'Mis mantenimientos' }} />
    </Stack>
  );
}

export default function RootLayout() {
  return (
    <AuthProvider>
      <StatusBar style="light" />
      <Navegacion />
    </AuthProvider>
  );
}
