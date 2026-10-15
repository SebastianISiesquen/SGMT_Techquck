import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import { api, cargarSesion, onSesionExpirada } from './api';
import type { Usuario } from './types';

interface AuthState {
  usuario: Usuario | null;
  cargando: boolean;
  ingresar: (email: string, password: string) => Promise<void>;
  salir: () => Promise<void>;
  refrescar: () => Promise<void>;
}

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [usuario, setUsuario] = useState<Usuario | null>(null);
  const [cargando, setCargando] = useState(true);

  useEffect(() => {
    onSesionExpirada(() => setUsuario(null));

    (async () => {
      try {
        if (await cargarSesion()) setUsuario(await api.me());
      } catch {
        setUsuario(null);
      } finally {
        setCargando(false);
      }
    })();
  }, []);

  const value: AuthState = {
    usuario,
    cargando,
    ingresar: async (email, password) => setUsuario(await api.login(email, password)),
    salir: async () => {
      await api.logout().catch(() => {});
      setUsuario(null);
    },
    refrescar: async () => setUsuario(await api.me()),
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth debe usarse dentro de AuthProvider');
  return ctx;
}
