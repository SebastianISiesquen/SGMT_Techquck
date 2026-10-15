import type { Equipo } from './types';

/**
 * Comunica el equipo escaneado a la pantalla que abrió el escáner (p. ej. la hoja de servicio):
 * el escáner lo deja aquí y la pantalla lo toma al volver a mostrarse.
 */
let pendiente: Equipo | null = null;

export function dejarEquipoEscaneado(e: Equipo) {
  pendiente = e;
}

export function tomarEquipoEscaneado(): Equipo | null {
  const e = pendiente;
  pendiente = null;
  return e;
}
