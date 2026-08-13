import { useState, useEffect, useCallback, useRef } from 'react';
import api from '../api/axios';
import { Navbar } from '../components/Navbar';
import { useColaEnVivo } from '../hooks/useColaEnVivo';
import { motivoDelFallo } from '../lib/errores';
import {
  Alert, Badge, Button, Card, CardBody, CardHeader,
  DataPoint, EmptyState, PageHeader,
} from '../components/ui';

// Mapea el estado que calcula el backend al tono de Badge/Card existente:
// sin tokens de color nuevos, reutiliza la misma paleta que el resto del sistema.
const TONO_POR_ESTADO = {
  atendiendo_a_tiempo: 'positive',
  retraso_critico: 'critical',
  en_limpieza: 'accent',
  vacia_por_inasistencia: 'warning',
  libre: 'neutral',
  sin_profesional: 'neutral',
};

export function AdminControlTower() {
  const [salas, setSalas] = useState([]);
  const [mensaje, setMensaje] = useState(null);
  const [sinPermiso, setSinPermiso] = useState(false);
  // Sala cuyo cambio de limpieza viaja: sin esto, dos clicks mandan dos
  // peticiones y el botón no da señal de haber registrado el primero.
  const [enVuelo, setEnVuelo] = useState(null);

  /*
    Salas que acaban de cambiar de estado. La pantalla se deja abierta en una
    pared: nadie la está leyendo cuando el estado cambia, y el movimiento es
    lo que hace que se note.

    Se anima el cambio, no el refresco: recargar cada 30 segundos y animar
    todo convertiría la señal en ruido de fondo.
  */
  const estadoPrevio = useRef(new Map());
  const [cambiaron, setCambiaron] = useState(() => new Set());

  const fetchSnapshot = useCallback(async () => {
    try {
      const res = await api.get('/admin/torre-control');
      setSalas(Array.isArray(res.data) ? res.data : (res.data.data ?? []));
    } catch (err) {
      console.error('No se pudo cargar la torre de control', err);
      if (err.response?.status === 403) setSinPermiso(true);
    }
  }, []);

  useEffect(() => {
    (async () => {
      await fetchSnapshot();
    })();
  }, [fetchSnapshot]);

  // Refresca al vuelo cuando alguna pantalla llama/atiende/marca ausente, y
  // cada 30s de respaldo para el retraso que aparece por el simple correr del
  // tiempo (sin que nadie dispare una acción nueva).
  useColaEnVivo(fetchSnapshot);

  useEffect(() => {
    const cambios = salas
      .filter((sala) => {
        const previo = estadoPrevio.current.get(sala.room_id);

        // En el primer render no hay contra qué comparar: sin esto la
        // pantalla arrancaría parpadeando entera.
        return previo !== undefined && previo !== sala.status;
      })
      .map((sala) => sala.room_id);

    estadoPrevio.current = new Map(salas.map((sala) => [sala.room_id, sala.status]));

    if (cambios.length > 0) setCambiaron(new Set(cambios));
  }, [salas]);

  const toggleLimpieza = async (sala) => {
    if (enVuelo) return;

    setMensaje(null);
    setEnVuelo(sala.room_id);

    try {
      await api.post(`/admin/salas/${sala.room_id}/limpieza`, { activar: !sala.en_limpieza });
      await fetchSnapshot();
    } catch (err) {
      console.error('No se pudo cambiar el estado de limpieza', err);
      setMensaje({
        tono: 'critical',
        texto: motivoDelFallo(err, 'No se pudo cambiar el estado de la sala.'),
      });
    } finally {
      setEnVuelo(null);
    }
  };

  if (sinPermiso) {
    return (
      <>
        <Navbar />
        <main className="mx-auto max-w-4xl px-6 py-8">
          <Alert tone="critical">No tenés permisos para ver la Torre de Control.</Alert>
        </main>
      </>
    );
  }

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-4xl px-6 py-8">
        <PageHeader
          title="Torre de Control"
          description="Estado en vivo de las salas: identificá el cuello de botella de un vistazo."
        />

        {mensaje && <Alert tone={mensaje.tono} className="mb-6">{mensaje.texto}</Alert>}

        {salas.length === 0 ? (
          <EmptyState>Sin salas configuradas.</EmptyState>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2">
            {salas.map((sala) => (
              <Card
                key={sala.room_id}
                className={cambiaron.has(sala.room_id) ? 'entra-fila' : undefined}
              >
                <CardHeader
                  title={sala.room_name}
                  description={sala.specialty ?? 'Sin especialidad'}
                  actions={<Badge tone={TONO_POR_ESTADO[sala.status] ?? 'neutral'}>{sala.status_label}</Badge>}
                />
                <CardBody className="space-y-4">
                  <dl className="grid grid-cols-2 gap-4">
                    <DataPoint label="Profesional" value={sala.professional ?? 'Sin asignar'} />
                    <DataPoint
                      label="Tiempo en estado"
                      value={sala.minutos_en_estado != null ? `${sala.minutos_en_estado} min` : '—'}
                    />
                    {sala.turno_relevante && (
                      <>
                        <DataPoint label="Turno" value={sala.turno_relevante.turno} />
                        <DataPoint label="Paciente" value={sala.turno_relevante.paciente ?? '—'} />
                      </>
                    )}
                  </dl>

                  {/* Se bloquean todas: el snapshot reordena la grilla, y dos
                      cambios a la vez dejarían botones desfasados. */}
                  <Button
                    variant="secondary"
                    className="w-full"
                    onClick={() => toggleLimpieza(sala)}
                    loading={enVuelo === sala.room_id}
                    disabled={Boolean(enVuelo)}
                  >
                    {sala.en_limpieza ? 'Marcar disponible' : 'Marcar en limpieza'}
                  </Button>
                </CardBody>
              </Card>
            ))}
          </div>
        )}
      </main>
    </>
  );
}
