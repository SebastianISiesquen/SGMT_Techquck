export const color = {
  brand: '#1f63c0',
  brandDark: '#0b1d3a',
  brandSoft: '#eff5ff',
  bg: '#f1f5f9',
  card: '#ffffff',
  border: '#e2e8f0',
  text: '#0f172a',
  textMuted: '#64748b',
  danger: '#dc2626',
  success: '#059669',
};

/** Mismos colores de estado que la web (fondo, texto). */
export const tono: Record<string, { bg: string; fg: string }> = {
  slate: { bg: '#f1f5f9', fg: '#334155' },
  blue: { bg: '#eff6ff', fg: '#1d4ed8' },
  green: { bg: '#ecfdf5', fg: '#047857' },
  amber: { bg: '#fffbeb', fg: '#92400e' },
  red: { bg: '#fef2f2', fg: '#b91c1c' },
  violet: { bg: '#f5f3ff', fg: '#6d28d9' },
};

export const colorEstado: Record<string, string> = {
  registrada: 'slate',
  asignada: 'blue',
  en_proceso: 'amber',
  resuelta: 'violet',
  cerrada: 'green',
  anulada: 'red',
  vigente: 'green',
  por_vencer: 'amber',
  vencida: 'red',
  resuelto: 'green',
  parcial: 'amber',
  pendiente_repuesto: 'blue',
  no_procede: 'red',
  pendiente_firma: 'amber',
  firmada: 'green',
  // Equipos
  operativo: 'green',
  observado: 'amber',
  con_falla: 'red',
  en_reparacion: 'blue',
  inoperativo: 'red',
  de_baja: 'slate',
};

export const colorPrioridad: Record<string, string> = {
  baja: 'slate',
  media: 'blue',
  alta: 'amber',
  critica: 'red',
};

const dos = (n: number) => String(n).padStart(2, '0');

/** dd/mm/aaaa hh:mm sin depender de Intl. */
export function fecha(iso?: string | null, conHora = true): string {
  if (!iso) return '—';
  const d = new Date(iso);
  const base = `${dos(d.getDate())}/${dos(d.getMonth() + 1)}/${d.getFullYear()}`;
  return conHora ? `${base} ${dos(d.getHours())}:${dos(d.getMinutes())}` : base;
}

/** Formato que espera Laravel: aaaa-mm-dd hh:mm:ss (hora local). */
export function fechaServidor(d: Date): string {
  return `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())} ${dos(d.getHours())}:${dos(d.getMinutes())}:00`;
}
