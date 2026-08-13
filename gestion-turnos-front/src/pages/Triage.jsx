import { useState, useEffect } from 'react';
import api from '../api/axios';
import { Navbar } from '../components/Navbar';
import { useColaEnVivo } from '../hooks/useColaEnVivo';
import {
  Alert, Badge, Button, Card, CardBody, CardHeader,
  EmptyState, Field, Input, PageHeader,
} from '../components/ui';

// Mismos cortes que el accessor `imc` del backend, para la vista previa en vivo.
function calcularImc(pesoKg, alturaCm) {
  const peso = parseFloat(pesoKg);
  const altura = parseFloat(alturaCm);
  if (!peso || !altura) return null;

  const metros = altura / 100;
  const imc = Math.round((peso / (metros * metros)) * 10) / 10;

  if (imc < 18.5) return { imc, categoria: 'Bajo peso', tono: 'warning' };
  if (imc < 25) return { imc, categoria: 'Normal', tono: 'positive' };
  if (imc < 30) return { imc, categoria: 'Sobrepeso', tono: 'warning' };

  return { imc, categoria: 'Obesidad', tono: 'critical' };
}

const soloAlfanumerico = (v = '') => v.replace(/[^0-9a-z]/gi, '').toLowerCase();

export function Triage() {
  const [appointments, setAppointments] = useState([]);
  const [seleccionado, setSeleccionado] = useState(null);

  const [busqueda, setBusqueda] = useState('');
  const [weight, setWeight] = useState('');
  const [height, setHeight] = useState('');
  const [bloodPressure, setBloodPressure] = useState('');
  const [mensaje, setMensaje] = useState(null);
  const [guardando, setGuardando] = useState(false);

  const cargarPendientes = async () => {
    try {
      const res = await api.get('/preconsulta/turnos');
      setAppointments(Array.isArray(res.data) ? res.data : (res.data.data ?? []));
    } catch (err) {
      console.error('Error al cargar turnos pendientes:', err);
      setMensaje({ tono: 'critical', texto: 'No se pudieron cargar los pacientes pendientes.' });
    }
  };

  useEffect(() => {
    (async () => {
      await cargarPendientes();
    })();
  }, []);

  // Aparece solo el paciente que Recepción acaba de registrar.
  useColaEnVivo(cargarPendientes);

  // El filtrado corre sobre la lista ya cargada, así que acá sí se puede buscar
  // por cédula parcial: los datos llegan descifrados al navegador.
  // (En el backend la cédula está cifrada y solo admite búsqueda exacta.)
  const termino = busqueda.trim().toLowerCase();
  const visibles = termino === ''
    ? appointments
    : appointments.filter((app) =>
      soloAlfanumerico(app.patient?.cedula).includes(soloAlfanumerico(termino))
        || (app.patient?.nombre ?? '').toLowerCase().includes(termino));

  const seleccionar = (app) => {
    setSeleccionado(app);
    setWeight('');
    setHeight('');
    setBloodPressure('');
    setMensaje(null);
  };

  const confirmar = async (e) => {
    e.preventDefault();
    if (!seleccionado || guardando) return;

    setMensaje(null);
    setGuardando(true);

    try {
      const res = await api.post(`/preconsulta/turnos/${seleccionado.id}/complete`, {
        weight: weight ? parseFloat(weight) : null,
        height: height ? parseFloat(height) : null,
        blood_pressure: bloodPressure,
      });

      const especialidad = res.data.data?.specialty?.name ?? seleccionado.specialty?.name;
      setMensaje({
        tono: 'positive',
        texto: `Turno ${seleccionado.turno} confirmado. Ya figura en el panel de ${especialidad}.`,
      });

      setSeleccionado(null);
      setBusqueda('');
      setWeight('');
      setHeight('');
      setBloodPressure('');

      cargarPendientes();
    } catch (err) {
      console.error('Error al completar preconsulta:', err.response?.data ?? err);
      const errores = err.response?.data?.errors;
      setMensaje({
        tono: 'critical',
        texto: errores
          ? Object.values(errores).flat().join(' ')
          : (err.response?.data?.message ?? 'No se pudo completar la preconsulta.'),
      });
    } finally {
      setGuardando(false);
    }
  };

  const resultadoImc = calcularImc(weight, height);

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-5xl px-6 py-8">
        <PageHeader
          title="Preconsulta"
          description="Buscá al paciente y registrá sus signos vitales."
        />

        {mensaje && <Alert tone={mensaje.tono} className="mb-6">{mensaje.texto}</Alert>}

        <div className="grid gap-6 lg:grid-cols-2">
          <Card>
            <CardHeader
              title="Pacientes en espera"
              description={termino === ''
                ? `${appointments.length} pendiente(s)`
                : `${visibles.length} de ${appointments.length}`}
            />
            <CardBody className="space-y-3">
              <Input
                type="search"
                value={busqueda}
                onChange={(e) => setBusqueda(e.target.value)}
                placeholder="Buscar por cédula o nombre"
                aria-label="Buscar paciente"
              />

              {visibles.length === 0 ? (
                <EmptyState>
                  {appointments.length === 0
                    ? 'No hay pacientes pendientes.'
                    : 'Ningún paciente coincide con la búsqueda.'}
                </EmptyState>
              ) : (
                <ul className="max-h-[26rem] space-y-2 overflow-y-auto">
                  {visibles.map((app) => {
                    const activo = seleccionado?.id === app.id;

                    return (
                      <li key={app.id}>
                        <button
                          type="button"
                          onClick={() => seleccionar(app)}
                          aria-pressed={activo}
                          className={`pulsable w-full rounded-control border px-3 py-2.5 text-left transition-colors ${
                            activo
                              ? 'border-accent bg-accent-soft'
                              : 'border-line hover-fino:hover:border-line-strong hover-fino:hover:bg-canvas'
                          }`}
                        >
                          <div className="flex items-baseline justify-between gap-3">
                            <span className="truncate text-sm font-medium">
                              {app.patient?.nombre}
                            </span>
                            <span className="tabular text-xs text-muted">
                              Turno {app.turno}
                            </span>
                          </div>
                          <div className="mt-1 flex items-center gap-2 text-xs text-muted">
                            <span className="tabular">{app.patient?.cedula}</span>
                            <span aria-hidden="true">·</span>
                            <span className="truncate">{app.specialty?.name}</span>
                          </div>
                        </button>
                      </li>
                    );
                  })}
                </ul>
              )}
            </CardBody>
          </Card>

          <Card>
            <CardHeader title="Signos vitales" />
            <CardBody>
              {seleccionado ? (
                <form onSubmit={confirmar} className="space-y-5">
                  <div className="rounded-control bg-canvas px-4 py-3">
                    <p className="text-sm font-semibold">{seleccionado.patient?.nombre}</p>
                    <p className="mt-0.5 text-xs text-muted">
                      <span className="tabular">{seleccionado.patient?.cedula}</span>
                      {' · '}
                      {seleccionado.specialty?.name}
                    </p>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <Field label="Peso (kg)" htmlFor="peso">
                      <Input
                        id="peso" type="number" step="0.1" min="0.5" max="400"
                        value={weight} onChange={(e) => setWeight(e.target.value)}
                        required placeholder="75.4" className="tabular"
                      />
                    </Field>

                    <Field label="Altura (cm)" htmlFor="altura">
                      <Input
                        id="altura" type="number" step="0.1" min="30" max="250"
                        value={height} onChange={(e) => setHeight(e.target.value)}
                        required placeholder="178" className="tabular"
                      />
                    </Field>
                  </div>

                  {/* El IMC se calcula mientras se escribe: permite detectar un
                      error de tipeo antes de confirmar. */}
                  {resultadoImc && (
                    <div className="flex items-center justify-between rounded-control border border-line bg-canvas px-4 py-3">
                      <span className="text-xs font-medium tracking-wide text-muted uppercase">
                        Índice de masa corporal
                      </span>
                      <span className="flex items-center gap-2">
                        <span className="tabular text-lg font-semibold">{resultadoImc.imc}</span>
                        <Badge tone={resultadoImc.tono}>{resultadoImc.categoria}</Badge>
                      </span>
                    </div>
                  )}

                  <Field label="Presión arterial" htmlFor="presion">
                    <Input
                      id="presion" value={bloodPressure}
                      onChange={(e) => setBloodPressure(e.target.value)}
                      required placeholder="120/80" className="tabular"
                    />
                  </Field>

                  <Button type="submit" disabled={guardando} className="w-full" size="lg">
                    {guardando ? 'Confirmando' : 'Confirmar'}
                  </Button>
                </form>
              ) : (
                <EmptyState>Seleccioná un paciente de la lista.</EmptyState>
              )}
            </CardBody>
          </Card>
        </div>
      </main>
    </>
  );
}
