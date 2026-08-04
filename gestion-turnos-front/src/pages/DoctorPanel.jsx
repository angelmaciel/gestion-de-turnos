import { useState, useEffect } from 'react';
import api from '../api/axios';
import { Navbar } from '../components/Navbar';
import { useColaEnVivo } from '../hooks/useColaEnVivo';
import {
  Alert, Badge, Button, Card, CardBody, CardHeader,
  DataPoint, EmptyState, PageHeader,
} from '../components/ui';

// Tono de la categoría de IMC que calcula el backend.
function tonoImc(categoria) {
  if (categoria === 'Normal') return 'positive';
  if (categoria === 'Obesidad') return 'critical';

  return 'warning'; // Bajo peso o Sobrepeso
}

export function DoctorPanel() {
  const [queue, setQueue] = useState([]);
  const [currentPatient, setCurrentPatient] = useState(null);
  const [mensaje, setMensaje] = useState(null);

  // 1. Cargar cola de espera (status = 'preconsulta_completa')
  const fetchQueue = async () => {
    try {
      const res = await api.get('/profesional/cola');
      setQueue(Array.isArray(res.data) ? res.data : (res.data.data ?? []));
    } catch (err) {
      console.error('Error al obtener la cola de espera:', err);
      if (err.response?.status === 403) {
        setMensaje({
          tono: 'critical',
          texto: 'Tu usuario no tiene un perfil de profesional configurado.',
        });
      }
    }
  };

  useEffect(() => {
    (async () => {
      await fetchQueue();
    })();
  }, []);

  // La cola se refresca sola cuando Preconsulta confirma un paciente
  // o cuando otro profesional de la especialidad toma un turno.
  useColaEnVivo(fetchQueue);

  // 2. Llamar a un paciente concreto, o al primero de la cola si no se indica uno.
  const handleCallPatient = async (targetPatient = null) => {
    setMensaje(null);
    const patientToCall = targetPatient ?? queue[0];

    if (!patientToCall) {
      setMensaje({ tono: 'neutral', texto: 'No hay pacientes en espera.' });

      return;
    }

    try {
      const res = await api.post(`/profesional/turnos/${patientToCall.id}/call`);
      setCurrentPatient(res.data.data ?? res.data);
      setMensaje({
        tono: 'positive',
        texto: `Turno ${patientToCall.turno} llamado al consultorio.`,
      });
      fetchQueue();
    } catch (err) {
      console.error('Error al llamar al paciente:', err);
      setMensaje({
        tono: 'critical',
        texto: err.response?.data?.message ?? 'No se pudo llamar al paciente.',
      });
    }
  };

  // 3. Re-llamar al paciente actual (incrementa 'attempts' en backend)
  const recallCurrent = async () => {
    if (!currentPatient) return;
    await handleCallPatient(currentPatient);
  };

  // 4. Marcar como Atendido (status -> 'atendido')
  const markAsAttended = async () => {
    if (!currentPatient) return;

    try {
      await api.post(`/profesional/turnos/${currentPatient.id}/attend`);
      setMensaje({
        tono: 'positive',
        texto: `Consulta finalizada para el turno ${currentPatient.turno}.`,
      });
      setCurrentPatient(null);
      fetchQueue();
    } catch (err) {
      console.error('Error al finalizar atención:', err);
      setMensaje({
        tono: 'critical',
        texto: err.response?.data?.message ?? 'No se pudo finalizar la atención.',
      });
    }
  };

  // 5. Marcar como Ausente (status -> 'ausente')
  const markAsAbsent = async () => {
    if (!currentPatient) return;

    try {
      await api.post(`/profesional/turnos/${currentPatient.id}/absent`);
      setMensaje({
        tono: 'warning',
        texto: `Turno ${currentPatient.turno} marcado como ausente.`,
      });
      setCurrentPatient(null);
      fetchQueue();
    } catch (err) {
      console.error('Error al marcar ausente:', err);
      setMensaje({
        tono: 'critical',
        texto: err.response?.data?.message ?? 'No se pudo marcar como ausente.',
      });
    }
  };

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-4xl px-6 py-8">
        <PageHeader title="Consultorio" description="Llamá y atendé a los pacientes de tu especialidad." />

        {mensaje && <Alert tone={mensaje.tono} className="mb-6">{mensaje.texto}</Alert>}

        <Card className="mb-6">
          <CardHeader title="En consultorio" />
          <CardBody>
            {currentPatient ? (
              <div className="space-y-5">
                <div className="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                  <span className="tabular text-4xl font-semibold text-accent">
                    {currentPatient.turno}
                  </span>
                  <div className="min-w-0">
                    <p className="text-lg font-semibold">{currentPatient.patient?.nombre}</p>
                    <p className="tabular text-sm text-muted">{currentPatient.patient?.cedula}</p>
                  </div>
                </div>

                <dl className="grid grid-cols-2 gap-4 rounded-control bg-canvas px-4 py-3 sm:grid-cols-5">
                  <DataPoint
                    label="Peso"
                    value={currentPatient.weight ? `${currentPatient.weight} kg` : '—'}
                  />
                  <DataPoint
                    label="Altura"
                    value={currentPatient.height ? `${currentPatient.height} cm` : '—'}
                  />
                  <div className="min-w-0">
                    <dt className="text-xs font-medium tracking-wide text-muted uppercase">IMC</dt>
                    <dd className="mt-0.5">
                      {currentPatient.imc ? (
                        <span className="flex flex-wrap items-center gap-1.5">
                          <span className="tabular text-sm font-semibold">{currentPatient.imc}</span>
                          <Badge tone={tonoImc(currentPatient.imc_categoria)}>
                            {currentPatient.imc_categoria}
                          </Badge>
                        </span>
                      ) : (
                        <span className="text-sm font-semibold">—</span>
                      )}
                    </dd>
                  </div>
                  <DataPoint label="Presión" value={currentPatient.blood_pressure ?? '—'} />
                  <DataPoint label="Llamados" value={currentPatient.attempts ?? 0} />
                </dl>

                <div className="flex flex-wrap gap-3">
                  <Button variant="secondary" onClick={recallCurrent} className="flex-1">
                    Volver a llamar
                  </Button>
                  <Button variant="positive" onClick={markAsAttended} className="flex-1">
                    Finalizar
                  </Button>
                  <Button variant="critical" onClick={markAsAbsent} className="flex-1">
                    Ausente
                  </Button>
                </div>
              </div>
            ) : (
              <div className="py-4 text-center">
                <p className="mb-4 text-sm text-subtle">Ningún paciente en atención.</p>
                <Button
                  size="lg"
                  onClick={() => handleCallPatient(null)}
                  disabled={queue.length === 0}
                >
                  Llamar siguiente
                </Button>
              </div>
            )}
          </CardBody>
        </Card>

        <Card>
          <CardHeader title="En espera" description={`${queue.length} paciente(s)`} />
          <CardBody className="p-0">
            {queue.length === 0 ? (
              <EmptyState>No hay pacientes esperando atención.</EmptyState>
            ) : (
              <ul className="divide-y divide-line">
                {queue.map((item) => (
                  <li key={item.id} className="flex items-center gap-4 px-5 py-3">
                    <span className="tabular w-10 shrink-0 text-sm font-semibold text-muted">
                      {item.turno}
                    </span>

                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-medium">{item.patient?.nombre}</p>
                      <p className="tabular mt-0.5 text-xs text-muted">
                        {item.weight ?? '—'} kg · {item.height ?? '—'} cm · {item.blood_pressure ?? '—'}
                        {item.imc && (
                          <>
                            {' · IMC '}
                            <span className="font-semibold">{item.imc}</span>
                          </>
                        )}
                      </p>
                    </div>

                    <Button variant="secondary" size="sm" onClick={() => handleCallPatient(item)}>
                      Llamar
                    </Button>
                  </li>
                ))}
              </ul>
            )}
          </CardBody>
        </Card>
      </main>
    </>
  );
}
