import type { ReactNode } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, TextInput, View, type TextInputProps, type ViewStyle } from 'react-native';
import { color, tono } from '@/lib/theme';

export function Badge({ texto, tono: t = 'slate' }: { texto: string; tono?: string }) {
  const c = tono[t] ?? tono.slate;
  return (
    <View style={[s.badge, { backgroundColor: c.bg }]}>
      <View style={[s.dot, { backgroundColor: c.fg }]} />
      <Text style={[s.badgeText, { color: c.fg }]}>{texto}</Text>
    </View>
  );
}

export function Card({ children, style, titulo }: { children: ReactNode; style?: ViewStyle; titulo?: string }) {
  return (
    <View style={[s.card, style]}>
      {titulo ? <Text style={s.cardTitle}>{titulo}</Text> : null}
      {children}
    </View>
  );
}

type Variante = 'primary' | 'secondary' | 'success' | 'danger';

export function Boton({
  texto, onPress, variante = 'primary', cargando, disabled, style,
}: { texto: string; onPress: () => void; variante?: Variante; cargando?: boolean; disabled?: boolean; style?: ViewStyle }) {
  const fondo = { primary: color.brand, secondary: '#fff', success: color.success, danger: color.danger }[variante];
  const fg = variante === 'secondary' ? color.text : '#fff';
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled || cargando}
      style={({ pressed }) => [
        s.boton,
        { backgroundColor: fondo, opacity: disabled ? 0.5 : pressed ? 0.85 : 1 },
        variante === 'secondary' && { borderWidth: 1, borderColor: color.border },
        style,
      ]}
    >
      {cargando ? <ActivityIndicator color={fg} /> : <Text style={[s.botonText, { color: fg }]}>{texto}</Text>}
    </Pressable>
  );
}

export function Campo({ label, requerido, ...props }: TextInputProps & { label: string; requerido?: boolean }) {
  return (
    <View style={{ marginBottom: 14 }}>
      <Text style={s.label}>
        {label} {requerido ? <Text style={{ color: color.danger }}>*</Text> : null}
      </Text>
      <TextInput placeholderTextColor="#94a3b8" style={[s.input, props.multiline && { minHeight: 90, textAlignVertical: 'top' }]} {...props} />
    </View>
  );
}

export function Chip({ texto, activo, onPress }: { texto: string; activo: boolean; onPress: () => void }) {
  return (
    <Pressable onPress={onPress} style={[s.chip, activo && { backgroundColor: color.brand, borderColor: color.brand }]}>
      <Text style={[s.chipText, activo && { color: '#fff' }]}>{texto}</Text>
    </Pressable>
  );
}

export function Fila({ label, valor }: { label: string; valor?: string | null }) {
  return (
    <View style={{ marginBottom: 10 }}>
      <Text style={s.filaLabel}>{label}</Text>
      <Text style={s.filaValor}>{valor || '—'}</Text>
    </View>
  );
}

export function Cargando() {
  return (
    <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: color.bg }}>
      <ActivityIndicator size="large" color={color.brand} />
    </View>
  );
}

const s = StyleSheet.create({
  badge: { flexDirection: 'row', alignItems: 'center', alignSelf: 'flex-start', gap: 5, borderRadius: 999, paddingHorizontal: 8, paddingVertical: 3 },
  dot: { width: 6, height: 6, borderRadius: 3 },
  badgeText: { fontSize: 12, fontWeight: '600' },
  card: { backgroundColor: color.card, borderRadius: 14, borderWidth: 1, borderColor: color.border, padding: 16, marginBottom: 12 },
  cardTitle: { fontSize: 13, fontWeight: '700', color: color.textMuted, textTransform: 'uppercase', letterSpacing: 0.5, marginBottom: 12 },
  boton: { minHeight: 48, borderRadius: 12, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 16 },
  botonText: { fontSize: 15, fontWeight: '700' },
  label: { fontSize: 14, fontWeight: '600', color: color.text, marginBottom: 6 },
  input: { backgroundColor: '#fff', borderWidth: 1, borderColor: '#cbd5e1', borderRadius: 10, paddingHorizontal: 12, paddingVertical: 11, fontSize: 15, color: color.text },
  chip: { borderWidth: 1, borderColor: color.border, backgroundColor: '#fff', borderRadius: 999, paddingHorizontal: 14, paddingVertical: 8 },
  chipText: { fontSize: 13, fontWeight: '600', color: color.textMuted },
  filaLabel: { fontSize: 11, fontWeight: '600', color: color.textMuted, textTransform: 'uppercase', letterSpacing: 0.4 },
  filaValor: { fontSize: 15, color: color.text, marginTop: 2 },
});
