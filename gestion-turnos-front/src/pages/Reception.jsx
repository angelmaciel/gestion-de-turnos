import { useState, useEffect } from 'react';
import api from '../api/axios';
import { Navbar } from '../components/Navbar';
import { Alert, Button, Card, CardBody, CardHeader, Field, Input, PageHeader, Select } from '../components/ui';

/*
  Las claves del formulario son las que valida el backend
  (`StoreAppointmentRequest`): así un 422 se vuelca campo por campo sin una
  tabla de traducción en el medio.
*/
const FORMULARIO_VACIO = { patient_dni: '', patient_name: '', specialty_id: '' };

/*
  El campo directamente no deja escribir lo que la regla del backend va a
  rechazar: descartar el caracter al tipearlo es más barato que mandar el
  formulario, esperar el 422 y volver a corregir.

  No reemplaza a la validación del servidor —que es la que vale para la
  auditoría— sino que evita llegar hasta ella por un punto de más.

  El nombre conserva acentos y ñ (\p{L}), las tildes que algunos teclados
  mandan como caracter combinante (\p{M}), y los apóstrofos y guiones de
  "D'Angelo" o "García-Ruiz". Pegar "1.234.567" en la cédula deja "1234567".
*/
const FILTROS = {
  patient_dni: (valor) => valor.replace(/\D+/g, ''),
  patient_name: (valor) => valor.replace(/[^\p{L}\p{M}\s'’-]+/gu, ''),
};

export function Reception() {
  const [form, setForm] = useState(FORMULARIO_VACIO);
  const [errores, setErrores] = useState({});
  const [avisoEnvio, setAvisoEnvio] = useState('');
  const [ultimoTurno, setUltimoTurno] = useState(null);

  const [especialidades, setEspecialidades] = useState([]);
  const [cargandoEspecialidades, setCargandoEspecialidades] = useState(true);
  // Va aparte del aviso de envío: si las especialidades no cargaron, el select
  // queda vacío y esa explicación tiene que sobrevivir a cada intento de alta.
  // Compartiendo estado, el primer submit la borraba y el campo se quedaba sin
  // opciones y sin motivo a la vista.
  const [errorEspecialidades, setErrorEspecialidades] = useState('');
  const [enviando, setEnviando] = useState(false);

  useEffect(() => {
    api.get('/especialidades')
      .then((res) => {
        setEspecialidades(Array.isArray(res.data) ? res.data : (res.data.data ?? []));
      })
      .catch((err) => {
        console.error('Error al cargar especialidades:', err.response ?? err);
        setErrorEspecialidades('No se pudieron cargar las especialidades. Recargá la página para reintentar.');
      })
      .finally(() => setCargandoEspecialidades(false));
  }, []);

  // Un error al lado de un campo que ya se está corrigiendo miente sobre el
  // estado actual del formulario: se limpia al primer tecleo.
  const editar = (campo) => (e) => {
    const value = FILTROS[campo] ? FILTROS[campo](e.target.value) : e.target.value;

    setForm((actual) => ({ ...actual, [campo]: value }));
    setAvisoEnvio('');
    setErrores((actuales) => {
      if (!actuales[campo]) return actuales;

      const resto = { ...actuales };
      delete resto[campo];
      return resto;
    });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (enviando) return;

    setAvisoEnvio('');
    setErrores({});
    setEnviando(true);

    // El nombre viaja sin espacios sobrantes: el backend igual los normaliza,
    // pero así lo que se manda es lo mismo que quedó guardado.
    const paciente = form.patient_name.trim().replace(/\s+/g, ' ');

    try {
      const { data } = await api.post('/entrada/turnos', { ...form, patient_name: paciente });

      setUltimoTurno({ numero: data.data?.turno ?? '', paciente });
      setForm(FORMULARIO_VACIO);
    } catch (err) {
      console.error('Error al registrar turno:', err.response ?? err);

      const status = err.response?.status;
      const porCampo = err.response?.data?.errors;

      if (status === 422 && porCampo) {
        // Laravel manda un arreglo de mensajes por campo. Abajo del campo entra
        // el primero: es el que dice qué corregir, y apilar el resto solo aleja
        // el foco del siguiente control.
        setErrores(
          Object.fromEntries(
            Object.entries(porCampo).map(([campo, mensajes]) => [
              campo,
              Array.isArray(mensajes) ? mensajes[0] : mensajes,
            ])
          )
        );
      } else if (!err.response) {
        setAvisoEnvio('No se pudo establecer conexión con el servidor.');
      } else if (status >= 500) {
        // Un 5xx no es culpa de lo que se escribió: marcar los campos en rojo
        // mandaría a corregir datos que estaban bien.
        setAvisoEnvio('El servidor no está respondiendo. Probá de nuevo en unos minutos.');
      } else {
        setAvisoEnvio(err.response.data?.message ?? 'No se pudo registrar el turno.');
      }
    } finally {
      setEnviando(false);
    }
  };

  const sinEspecialidades = cargandoEspecialidades || Boolean(errorEspecialidades);

  return (
    <>
      <Navbar />
      {/* Una columna angosta: son tres campos cortos y a lo ancho de la
          pantalla el recorrido del ojo entre etiqueta y campo se estira sin
          que entre más información. */}
      <main className="mx-auto max-w-xl px-6 py-8">
        <PageHeader
          title="Mesa de entrada"
          description="Registrá al paciente y asignale una especialidad."
        />

        {errorEspecialidades && (
          <Alert tone="critical" className="mb-4">
            {errorEspecialidades}
          </Alert>
        )}

        {/* El número de turno es lo que hay que decirle al paciente en voz
            alta, así que se muestra grande y en cifras tabulares, no dentro de
            una oración. Queda arriba del formulario —que ya se vació— porque
            ahí es donde vuelve la vista después de enviar. */}
        {ultimoTurno && (
          <Card role="status" className="mb-4 border-positive/30 bg-positive-soft">
            <CardBody className="flex items-center gap-4">
              <div className="min-w-0">
                <p className="text-xs font-medium tracking-wide text-positive uppercase">
                  Último turno registrado
                </p>
                <p className="mt-1 truncate text-sm text-ink">
                  {ultimoTurno.paciente} pasa a preconsulta.
                </p>
              </div>
              <p className="ml-auto text-3xl font-semibold tabular text-positive">
                {ultimoTurno.numero}
              </p>
            </CardBody>
          </Card>
        )}

        <Card>
          <CardHeader title="Nuevo turno" description="Los tres datos son obligatorios." />
          <CardBody>
            <form onSubmit={handleSubmit} className="space-y-5">
              {/* Arriba y no debajo del botón: después de enviar, la vista
                  queda en el formulario y un aviso al pie pasa desapercibido. */}
              {avisoEnvio && <Alert tone="critical">{avisoEnvio}</Alert>}

              {/* Durante el envío el formulario entero queda inerte: editar un
                  campo cuya petición ya salió no cambia el resultado. */}
              <fieldset disabled={enviando} className="space-y-5">
                <Field label="Cédula de identidad" htmlFor="cedula" error={errores.patient_dni}>
                  <Input
                    id="cedula"
                    value={form.patient_dni}
                    onChange={editar('patient_dni')}
                    required
                    autoFocus
                    inputMode="numeric"
                    maxLength={20}
                    autoComplete="off"
                    placeholder="5384891"
                    className="tabular"
                    invalid={Boolean(errores.patient_dni)}
                    aria-describedby={errores.patient_dni ? 'cedula-error' : undefined}
                  />
                </Field>

                <Field label="Nombre y apellido" htmlFor="nombre" error={errores.patient_name}>
                  <Input
                    id="nombre"
                    value={form.patient_name}
                    onChange={editar('patient_name')}
                    required
                    maxLength={100}
                    autoComplete="off"
                    placeholder="Nombre del paciente"
                    invalid={Boolean(errores.patient_name)}
                    aria-describedby={errores.patient_name ? 'nombre-error' : undefined}
                  />
                </Field>

                <Field label="Especialidad" htmlFor="especialidad" error={errores.specialty_id}>
                  <Select
                    id="especialidad"
                    value={form.specialty_id}
                    onChange={editar('specialty_id')}
                    required
                    disabled={sinEspecialidades}
                    aria-busy={cargandoEspecialidades || undefined}
                    invalid={Boolean(errores.specialty_id)}
                    aria-describedby={errores.specialty_id ? 'especialidad-error' : undefined}
                  >
                    <option value="">
                      {cargandoEspecialidades ? 'Cargando…' : 'Seleccionar'}
                    </option>
                    {especialidades.map((spec) => (
                      <option key={spec.id} value={spec.id}>
                        {spec.name}
                      </option>
                    ))}
                  </Select>
                </Field>
              </fieldset>

              <Button
                type="submit"
                loading={enviando}
                disabled={sinEspecialidades}
                className="w-full"
                size="lg"
              >
                {enviando ? 'Registrando…' : 'Registrar turno'}
              </Button>
            </form>
          </CardBody>
        </Card>
      </main>
    </>
  );
}
