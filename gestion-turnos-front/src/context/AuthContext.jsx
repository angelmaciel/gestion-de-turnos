import { useEffect, useState, useCallback } from 'react';
import api, { obtenerCookieCsrf } from '../api/axios';
import { AuthContext } from './authContextValue';

/**
 * Estado de sesión del SPA.
 *
 * No guarda nada en localStorage ni sessionStorage: la sesión vive en una
 * cookie HttpOnly que JavaScript no puede leer, y los datos del usuario se
 * piden a /auth/me en cada carga. Así un XSS no puede robar la sesión, y los
 * roles nunca quedan desactualizados respecto del backend.
 */
export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  // `cargando` evita que las rutas protegidas reboten al login mientras
  // todavía no se sabe si hay sesión abierta.
  const [cargando, setCargando] = useState(true);

  const refrescarUsuario = useCallback(async () => {
    try {
      const { data } = await api.get('/auth/me');
      setUser(data.user);

      return data.user;
    } catch {
      // 401 es la respuesta normal cuando no hay sesión: no es un error.
      setUser(null);

      return null;
    }
  }, []);

  useEffect(() => {
    let vigente = true;

    (async () => {
      await refrescarUsuario();
      if (vigente) setCargando(false);
    })();

    return () => {
      vigente = false;
    };
  }, [refrescarUsuario]);

  const login = useCallback(async (email, password) => {
    // Sin la cookie CSRF previa, el POST sería rechazado con 419.
    await obtenerCookieCsrf();
    const { data } = await api.post('/auth/login', { email, password });
    setUser(data.user);

    return data.user;
  }, []);

  const logout = useCallback(async () => {
    try {
      await api.post('/auth/logout');
    } finally {
      // Aunque el backend falle, en el cliente la sesión se da por terminada.
      setUser(null);
    }
  }, []);

  const roles = (Array.isArray(user?.roles) ? user.roles : [])
    .map((r) => (typeof r === 'object' ? r?.name : r))
    .filter(Boolean);

  return (
    <AuthContext.Provider value={{ user, roles, cargando, login, logout, refrescarUsuario }}>
      {children}
    </AuthContext.Provider>
  );
}
