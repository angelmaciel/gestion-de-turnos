/**
 * Traduce un fallo de petición a algo que se pueda leer desde el puesto de
 * trabajo.
 *
 * Un 5xx o una caída de red no son culpa de quien tocó el botón: mostrarle el
 * mensaje del negocio lo manda a buscar el problema donde no está.
 *
 * @param {unknown} err        Error de axios.
 * @param {string} porDefecto  Motivo del negocio, si el servidor no manda uno.
 */
export function motivoDelFallo(err, porDefecto) {
  if (!err?.response) return 'No se pudo establecer conexión con el servidor.';
  if (err.response.status >= 500) {
    return 'El servidor no está respondiendo. Probá de nuevo en unos minutos.';
  }

  return err.response.data?.message ?? porDefecto;
}
