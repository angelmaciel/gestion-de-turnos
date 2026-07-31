import { useEffect, useRef } from 'react';
import { echo } from '../services/echo';

/**
 * Ejecuta `recargar` cada vez que el backend avisa que cambió alguna cola.
 *
 * El evento no trae datos: es solo una señal. Cada pantalla vuelve a pedir su
 * propia lista al endpoint autenticado, que filtra por rol y especialidad.
 *
 * Incluye un refresco periódico de respaldo por si el WebSocket se cae.
 */
export function useColaEnVivo(recargar, { intervaloRespaldo = 30000 } = {}) {
  // El listener se registra una sola vez; sin ref usaría la primera versión
  // de `recargar` y leería estado viejo.
  const recargarRef = useRef(recargar);
  useEffect(() => {
    recargarRef.current = recargar;
  }, [recargar]);

  useEffect(() => {
    const canal = echo.channel('cola-turnos');
    canal.listen('.cola.actualizada', () => recargarRef.current?.());

    const respaldo = setInterval(() => recargarRef.current?.(), intervaloRespaldo);

    return () => {
      clearInterval(respaldo);
      echo.leaveChannel('cola-turnos');
    };
  }, [intervaloRespaldo]);
}
