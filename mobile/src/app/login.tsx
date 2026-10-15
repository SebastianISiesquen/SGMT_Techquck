import { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Boton, Campo } from '@/components/ui';
import { getServidor, setServidor } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { color } from '@/lib/theme';

export default function Login() {
  const { ingresar } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [servidor, setServidorLocal] = useState(getServidor());
  const [verServidor, setVerServidor] = useState(false);
  const [error, setError] = useState('');
  const [enviando, setEnviando] = useState(false);

  const enviar = async () => {
    setError('');
    setEnviando(true);
    try {
      await setServidor(servidor);
      await ingresar(email.trim(), password);
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setEnviando(false);
    }
  };

  return (
    <SafeAreaView style={{ flex: 1, backgroundColor: color.brandDark }}>
      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView contentContainerStyle={s.contenedor} keyboardShouldPersistTaps="handled">
          <View style={s.marca}>
            <View style={s.logo}><Text style={{ fontSize: 30 }}>🛡️</Text></View>
            <Text style={s.titulo}>SGGP Técnico</Text>
            <Text style={s.subtitulo}>Atención de incidencias en garantía · TECHQUK</Text>
          </View>

          <View style={s.panel}>
            <Campo label="Correo" value={email} onChangeText={setEmail} autoCapitalize="none" keyboardType="email-address" autoComplete="email" placeholder="tecnico@techquk.com.pe" />
            <Campo label="Contraseña" value={password} onChangeText={setPassword} secureTextEntry autoComplete="password" onSubmitEditing={enviar} />

            {verServidor ? (
              <Campo label="Servidor" value={servidor} onChangeText={setServidorLocal} autoCapitalize="none" keyboardType="url" placeholder="https://sggp.techquk.com.pe" />
            ) : null}

            {error ? <Text style={s.error}>{error}</Text> : null}

            <Boton texto="Ingresar" onPress={enviar} cargando={enviando} disabled={!email || !password} />

            <Pressable onPress={() => setVerServidor(!verServidor)} style={{ marginTop: 16, alignSelf: 'center' }}>
              <Text style={{ color: color.textMuted, fontSize: 12 }}>{verServidor ? 'Ocultar servidor' : `Servidor: ${servidor}`}</Text>
            </Pressable>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const s = StyleSheet.create({
  contenedor: { flexGrow: 1, justifyContent: 'center', padding: 20 },
  marca: { alignItems: 'center', marginBottom: 28 },
  logo: { width: 64, height: 64, borderRadius: 18, backgroundColor: color.brand, alignItems: 'center', justifyContent: 'center', marginBottom: 12 },
  titulo: { color: '#fff', fontSize: 26, fontWeight: '800' },
  subtitulo: { color: '#94a3b8', fontSize: 13, marginTop: 4, textAlign: 'center' },
  panel: { backgroundColor: '#fff', borderRadius: 20, padding: 20 },
  error: { color: color.danger, backgroundColor: '#fef2f2', padding: 10, borderRadius: 8, marginBottom: 12, fontSize: 13 },
});
