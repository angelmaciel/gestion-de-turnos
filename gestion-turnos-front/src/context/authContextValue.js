import { createContext, useContext } from 'react';

/**
 * Contexto y hook viven aparte del componente AuthProvider para que el archivo
 * del provider exporte solo componentes: mezclarlos rompe el fast refresh
 * de Vite en desarrollo.
 */
export const AuthContext = createContext(null);

export function useAuth() {
  const ctx = useContext(AuthContext);

  if (!ctx) {
    throw new Error('useAuth debe usarse dentro de <AuthProvider>');
  }

  return ctx;
}
