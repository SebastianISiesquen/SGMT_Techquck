import * as SecureStore from 'expo-secure-store';
import type { ArticuloAlmacen, Equipo, HojaServicio, Incidencia, Mantenimiento, Notificacion, ProyectoOpcion, Resumen, Usuario } from './types';

/**
 * URL del backend Laravel. En producción se define al compilar con
 * EXPO_PUBLIC_API_URL=https://sggp.techquk.com.pe ; en desarrollo puede
 * cambiarse desde la pantalla de inicio de sesión (IP de la PC en la red local).
 */
const URL_POR_DEFECTO = process.env.EXPO_PUBLIC_API_URL ?? 'http://192.168.1.10:8000';

const K_TOKEN = 'sggp_token';
const K_SERVIDOR = 'sggp_servidor';

let token: string | null = null;
let servidor = URL_POR_DEFECTO;
let alExpirar: (() => void) | null = null;

export class ApiError extends Error {
  constructor(message: string, public status: number) {
    super(message);
  }
}

export async function cargarSesion(): Promise<boolean> {
  token = await SecureStore.getItemAsync(K_TOKEN);
  servidor = (await SecureStore.getItemAsync(K_SERVIDOR)) ?? URL_POR_DEFECTO;
  return !!token;
}

export function getServidor() {
  return servidor;
}

export async function setServidor(url: string) {
  servidor = url.trim().replace(/\/+$/, '');
  await SecureStore.setItemAsync(K_SERVIDOR, servidor);
}

export function onSesionExpirada(cb: () => void) {
  alExpirar = cb;
}

/** Cabeceras para cargar imágenes protegidas (<Image source={{ uri, headers }} />). */
export function authHeaders(): Record<string, string> {
  return token ? { Authorization: `Bearer ${token}` } : {};
}

async function request<T>(path: string, init: { method?: string; body?: FormData | object } = {}): Promise<T> {
  const esFormData = init.body instanceof FormData;
  let res: Response;

  try {
    res = await fetch(`${servidor}/api${path}`, {
      method: init.method ?? 'GET',
      headers: {
        Accept: 'application/json',
        ...(init.body && !esFormData ? { 'Content-Type': 'application/json' } : {}),
        ...authHeaders(),
      },
      body: init.body ? (esFormData ? (init.body as FormData) : JSON.stringify(init.body)) : undefined,
    });
  } catch {
    throw new ApiError(`No se pudo conectar con el servidor (${servidor}). Verifique su conexión.`, 0);
  }

  if (res.status === 401) {
    await cerrarSesionLocal();
    alExpirar?.();
    throw new ApiError('Su sesión expiró. Ingrese nuevamente.', 401);
  }

  if (res.status === 204) return undefined as T;

  const data = await res.json().catch(() => ({}));

  if (!res.ok) {
    // Errores de validación de Laravel: { message, errors: { campo: [msg] } }
    const primero = data?.errors ? (Object.values(data.errors)[0] as string[])[0] : null;
    throw new ApiError(primero ?? data?.message ?? `Error ${res.status}`, res.status);
  }

  return data as T;
}

async function cerrarSesionLocal() {
  token = null;
  await SecureStore.deleteItemAsync(K_TOKEN);
}

export const api = {
  async login(email: string, password: string) {
    const r = await request<{ token: string; usuario: Usuario }>('/login', {
      method: 'POST',
      body: { email, password, dispositivo: 'app-movil' },
    });
    token = r.token;
    await SecureStore.setItemAsync(K_TOKEN, r.token);
    return r.usuario;
  },

  async logout() {
    try {
      await request('/logout', { method: 'POST' });
    } finally {
      await cerrarSesionLocal();
    }
  },

  me: () => request<{ usuario: Usuario }>('/me').then((r) => r.usuario),
  resumen: () => request<Resumen>('/resumen'),
  incidencias: (estado: string) =>
    request<{ data: Incidencia[] }>(`/incidencias?estado=${encodeURIComponent(estado)}`).then((r) => r.data),
  incidencia: (id: number | string) => request<{ data: Incidencia }>(`/incidencias/${id}`).then((r) => r.data),
  iniciar: (id: number | string) => request<{ data: Incidencia }>(`/incidencias/${id}/iniciar`, { method: 'POST' }),
  registrarAtencion: (id: number | string, form: FormData) =>
    request<{ data: Incidencia }>(`/incidencias/${id}/atenciones`, { method: 'POST', body: form }).then((r) => r.data),
  subirArchivo: (id: number | string, form: FormData) =>
    request(`/incidencias/${id}/archivos`, { method: 'POST', body: form }),
  notificaciones: () => request<Notificacion[]>('/notificaciones'),
  proyectos: () => request<ProyectoOpcion[]>('/proyectos'),
  hojas: () => request<{ data: HojaServicio[] }>('/hojas').then((r) => r.data),
  hoja: (id: number | string) => request<{ data: HojaServicio }>(`/hojas/${id}`).then((r) => r.data),
  crearHoja: (form: FormData) => request<{ data: HojaServicio }>('/hojas', { method: 'POST', body: form }).then((r) => r.data),
  /** Acta de conformidad firmada por el cliente (foto o PDF): la hoja queda conforme. */
  subirActa: (id: number | string, form: FormData) =>
    request<{ data: HojaServicio }>(`/hojas/${id}/acta`, { method: 'POST', body: form }).then((r) => r.data),
  verificaciones: () => request<string[]>('/verificaciones'),
  leerNotificaciones: () => request('/notificaciones/leer', { method: 'POST' }),
  buscarEquipos: (texto: string) => request<{ data: Equipo[] }>(`/equipos?buscar=${encodeURIComponent(texto)}`).then((r) => r.data),
  equiposDeProyecto: (proyectoId: number) => request<{ data: Equipo[] }>(`/equipos?proyecto_id=${proyectoId}`).then((r) => r.data),
  equipo: (id: number | string) => request<{ data: Equipo }>(`/equipos/${id}`).then((r) => r.data),
  /** Acepta lo que lee el escáner: la URL del QR del sticker, el código EQ-… o la serie. */
  equipoPorCodigo: (texto: string) => request<{ data: Equipo }>(`/equipos/codigo/${encodeURIComponent(texto)}`).then((r) => r.data),
  mantenimientos: () => request<Mantenimiento[]>('/mantenimientos'),
  almacen: () => request<ArticuloAlmacen[]>('/almacen'),
  /** { categoría normalizada: [puntos de control] } */
  checklists: () => request<Record<string, string[]>>('/checklists'),
};
