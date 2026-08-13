import { useState, useEffect, useRef } from 'react';
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

/*
  Reglas del cliente, espejo de las de CompletePreconsultaRequest.

  Existen por el mismo motivo que en mesa de entrada: con `required`, `min` y
  `max` nativos el navegador frena el envío con su propio globo, la petición
  nunca sale y el mensaje por campo —que es el que este formulario dibuja— no
  llega a mostrarse jamás. El formulario lleva `noValidate` y la comprobación
  la hace esta tabla, así que hay una sola forma de decir que algo está mal.

  El backend los acepta nulos; acá son obligatorios porque una preconsulta sin
  signos vitales no es una preconsulta.
*/
const REGLAS = {
  weight: {
    exigir: (v) => {
      if (v.trim() === '') return 'Ingresá el peso del paciente.';
      const n = parseFloat(v);
      if (Number.isNaN(n)) return 'El peso debe ser un número.';
      if (n < 0.5 || n > 400) return 'El peso debe estar entre 0,5 y 400 kg.';
      return '';
    },
  },
  height: {
    exigir: (v) => {
      if (v.trim() === '') return 'Ingresá la altura del paciente.';
      const n = parseFloat(v);
      if (Number.isNaN(n)) return 'La altura debe ser un número.';
      if (n < 30 || n > 250) return 'La altura debe estar entre 30 y 250 cm.';
      return '';
    },
  },
  blood_pressure: {
    exigir: (v) => {
      if (v.trim() === '') return 'Ingresá la presión arterial.';
      if (v.length > 20) return 'La presión arterial no puede pasar de 20 caracteres.';
      return '';
    },
  },
};

const CAMPOS = Object.keys(REGLAS);

export function Triage() {
  const [appointments, setAppointments] = useState([]);
  const [seleccionado, setSeleccionado] = useState(null);

  const [busqueda, setBusqueda] = useState('');
  const [weight, setWeight] = useState('');
  const [height, setHeight] = useState('');
  const [bloodPressure, setBloodPressure] = useState('');
  const [errores, setErrores] = useState({});
  const [resultado, setResultado] = useState(null);
  // Aparte del resultado del guardado: si la cola no cargó, la lista queda
  // vacía y esa explicación tiene que sobrevivir a cada intento de confirmar.
  const [errorCarga, setErrorCarga] = useState('');
  const [guardando, setGuardando] = useState(false);

  const pesoRef = useRef(null);
  const alturaRef = useRef(null);
  const presionRef = useRef(null);

  const marcar = (campo, mensaje) => {
    setErrores((actuales) => {
      if ((actuales[campo] ?? '') === mensaje) return actuales;

      const siguiente = { ...actuales };
      if (mensaje) siguiente[campo] = mensaje;
      else delete siguiente[campo];
      return siguiente;
    });
  };

  // Al salir del campo, no mientras se escribe: avisar a mitad de un número
  // que todavía se está tipeando es corregir antes de que haya un error.
  const revisar = (campo, valor) => () => marcar(campo, REGLAS[campo].exigir(valor));

  const editar = (campo, setter) => (e) => {
    setter(e.target.value);
    marcar(campo, '');
  };

  /*
    La animación de entrada la reciben solo las filas que acaban de aparecer:
    la cola se refresca por WebSocket y cada 30 segundos, así que animarla
    entera en cada refresco sería movimiento sin motivo varias veces por
    minuto, en una pantalla que se mira todo el día.

    `idsPrevios` es un ref porque cambiarlo no debe provocar un render, y se
    lee únicamente dentro del efecto: consultarlo durante el pintado no es
    seguro con render concurrente.

    `nuevos` sí es estado, porque de él depende lo que se pinta. Se conserva
    hasta que llegue la próxima tanda en vez de limpiarse enseguida: vaciarlo
    justo después de pintar sacaría la clase a mitad de la animación y la
    cortaría por la mitad.
  */
  const idsPrevios = useRef(new Set());
  const [nuevos, setNuevos] = useState(() => new Set());

  const cargarPendientes = async () => {
    try {
      const res = await api.get('/preconsulta/turnos');
      setAppointments(Array.isArray(res.data) ? res.data : (res.data.data ?? []));
      setErrorCarga('');
    } catch (err) {
      console.error('Error al cargar turnos pendientes:', err);
      setErrorCarga('No se pudieron cargar los pacientes pendientes.');
    }
  };

  useEffect(() => {
    (async () => {
      await cargarPendientes();
    })();
  }, []);

  // Aparece solo el paciente que Mesa de entrada acaba de registrar.
  useColaEnVivo(cargarPendientes);

  useEffect(() => {
    const actuales = new Set(appointments.map((app) => app.id));
    const llegaron = [...actuales].filter((id) => !idsPrevios.current.has(id));

    // Se guarda el conjunto actual, no la unión: si un turno vuelve a
    // preconsulta más tarde corresponde que se anuncie de nuevo, y acumulando
    // todo el conjunto crecería durante toda la jornada.
    idsPrevios.current = actuales;

    if (llegaron.length > 0) setNuevos(new Set(llegaron));
  }, [appointments]);

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
    setErrores({});
    setResultado(null);

    // El foco salta al peso: elegir al paciente y empezar a tipear es un solo
    // gesto, sin volver al mouse para picar el primer campo. Va en el efecto
    // del siguiente pintado porque el formulario todavía no existe: hasta
    // ahora la tarjeta mostraba el estado vacío.
    requestAnimationFrame(() => pesoRef.current?.focus());
  };

  const confirmar = async (e) => {
    e.preventDefault();
    if (!seleccionado || guardando) return;

    setResultado(null);

    const valores = { weight, height, blood_pressure: bloodPressure };
    const faltantes = {};

    for (const campo of CAMPOS) {
      const mensaje = REGLAS[campo].exigir(valores[campo]);
      if (mensaje) faltantes[campo] = mensaje;
    }

    if (Object.keys(faltantes).length > 0) {
      setErrores(faltantes);

      // Al primero que falta, en el orden en que están en pantalla.
      const primero = CAMPOS.find((campo) => faltantes[campo]);
      const destino = { weight: pesoRef, height: alturaRef, blood_pressure: presionRef }[primero];
      destino.current?.focus();

      return;
    }

    setErrores({});
    setGuardando(true);

    try {
      const res = await api.post(`/preconsulta/turnos/${seleccionado.id}/complete`, {
        weight: weight ? parseFloat(weight) : null,
        height: height ? parseFloat(height) : null,
        blood_pressure: bloodPressure,
      });

      const especialidad = res.data.data?.specialty?.name ?? seleccionado.specialty?.name;
      setResultado({
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

      const status = err.response?.status;
      const porCampo = err.response?.data?.errors;

      if (status === 422 && porCampo) {
        // Cada mensaje va bajo su campo, igual que en mesa de entrada:
        // apilarlos todos en un párrafo obliga a adivinar cuál corregir.
        setErrores(
          Object.fromEntries(
            Object.entries(porCampo).map(([campo, mensajes]) => [
              campo,
              Array.isArray(mensajes) ? mensajes[0] : mensajes,
            ])
          )
        );
      } else if (!err.response) {
        setResultado({ tono: 'critical', texto: 'No se pudo establecer conexión con el servidor.' });
      } else if (status >= 500) {
        // Un 5xx no es culpa de lo que se midió: marcar los campos en rojo
        // mandaría a corregir valores que estaban bien.
        setResultado({ tono: 'critical', texto: 'El servidor no está respondiendo. Probá de nuevo en unos minutos.' });
      } else {
        setResultado({
          tono: 'critical',
          texto: err.response.data?.message ?? 'No se pudo completar la preconsulta.',
        });
      }
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

        {errorCarga && <Alert tone="critical" className="mb-6">{errorCarga}</Alert>}
        {resultado && <Alert tone={resultado.tono} className="mb-6">{resultado.texto}</Alert>}

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
                    // Solo entra con animación la fila que no estaba antes.
                    const recienLlegado = nuevos.has(app.id);

                    return (
                      <li key={app.id} className={recienLlegado ? 'entra-fila' : undefined}>
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
                <form onSubmit={confirmar} className="space-y-5" noValidate>
                  {/* Vuelve a entrar cada vez que cambia el paciente: confirma
                      que la tarjeta ahora habla de otra persona, que es el
                      error mas caro posible en esta pantalla. */}
                  <div key={seleccionado.id} className="entra-fila rounded-control bg-canvas px-4 py-3">
                    <p className="text-sm font-semibold">{seleccionado.patient?.nombre}</p>
                    <p className="mt-0.5 text-xs text-muted">
                      <span className="tabular">{seleccionado.patient?.cedula}</span>
                      {' · '}
                      {seleccionado.specialty?.name}
                    </p>
                  </div>

                  {/* Inerte mientras la peticion viaja: cambiar una medicion ya
                      enviada no cambia lo que se guardo. */}
                  <fieldset disabled={guardando} className="space-y-5">
                    <div className="grid grid-cols-2 gap-4">
                      <Field label="Peso (kg)" htmlFor="peso" error={errores.weight}>
                        {/* Sin `required`, `min` ni `max` nativos: los frenaba
                            el navegador con su propio globo y el mensaje bajo
                            el campo no llegaba a mostrarse. La comprobacion la
                            hace REGLAS, espejo de la del backend. */}
                        <Input
                          id="peso" ref={pesoRef} type="number" step="0.1" inputMode="decimal"
                          value={weight} onChange={editar('weight', setWeight)}
                          onBlur={revisar('weight', weight)}
                          placeholder="75.4" className="tabular"
                          invalid={Boolean(errores.weight)}
                          aria-describedby={errores.weight ? 'peso-error' : undefined}
                        />
                      </Field>

                      <Field label="Altura (cm)" htmlFor="altura" error={errores.height}>
                        <Input
                          id="altura" ref={alturaRef} type="number" step="0.1" inputMode="decimal"
                          value={height} onChange={editar('height', setHeight)}
                          onBlur={revisar('height', height)}
                          placeholder="178" className="tabular"
                          invalid={Boolean(errores.height)}
                          aria-describedby={errores.height ? 'altura-error' : undefined}
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

                    <Field label="Presión arterial" htmlFor="presion" error={errores.blood_pressure}>
                      <Input
                        id="presion" ref={presionRef} value={bloodPressure}
                        onChange={editar('blood_pressure', setBloodPressure)}
                        onBlur={revisar('blood_pressure', bloodPressure)}
                        maxLength={20} placeholder="120/80" className="tabular"
                        invalid={Boolean(errores.blood_pressure)}
                        aria-describedby={errores.blood_pressure ? 'presion-error' : undefined}
                      />
                    </Field>
                  </fieldset>

                  <Button type="submit" loading={guardando} className="w-full" size="lg">
                    {guardando ? 'Confirmando…' : 'Confirmar'}
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
