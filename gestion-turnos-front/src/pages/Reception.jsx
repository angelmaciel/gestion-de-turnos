import { useState, useEffect } from 'react';
import api from '../api/axios';
import { Navbar } from '../components/Navbar';
import { Alert, Button, Card, CardBody, Field, Input, PageHeader, Select } from '../components/ui';

export function Reception() {
  const [cedula, setCedula] = useState('');
  const [nombre, setNombre] = useState('');
  const [specialtyId, setSpecialtyId] = useState('');

  const [specialties, setSpecialties] = useState([]);
  const [mensaje, setMensaje] = useState(null);
  const [cargandoEspecialidades, setCargandoEspecialidades] = useState(true);
  const [enviando, setEnviando] = useState(false);

  useEffect(() => {
    api.get('/especialidades')
      .then((res) => {
        setSpecialties(Array.isArray(res.data) ? res.data : (res.data.data ?? []));
      })
      .catch((err) => {
        console.error('Error al cargar especialidades:', err.response ?? err);
        setMensaje({ tono: 'critical', texto: 'No se pudieron cargar las especialidades.' });
      })
      .finally(() => setCargandoEspecialidades(false));
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (enviando) return;

    setMensaje(null);
    setEnviando(true);

    try {
      const { data } = await api.post('/entrada/turnos', {
        patient_dni: cedula,
        patient_name: nombre,
        specialty_id: specialtyId,
      });

      setMensaje({
        tono: 'positive',
        texto: `Turno ${data.data?.turno ?? ''} registrado. El paciente pasa a preconsulta.`,
      });

      setCedula('');
      setNombre('');
      setSpecialtyId('');
    } catch (err) {
      console.error('Error al registrar turno:', err.response);

      const errores = err.response?.data?.errors;
      setMensaje({
        tono: 'critical',
        texto: errores
          ? Object.values(errores).flat().join(' ')
          : (err.response?.data?.message ?? 'No se pudo registrar el turno.'),
      });
    } finally {
      setEnviando(false);
    }
  };

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-2xl px-6 py-8">
        <PageHeader
          title="Recepción"
          description="Registrá al paciente y asignale una especialidad."
        />

        <Card>
          <CardBody>
            <form onSubmit={handleSubmit} className="space-y-5">
              <Field label="Cédula de identidad" htmlFor="cedula">
                <Input
                  id="cedula"
                  value={cedula}
                  onChange={(e) => setCedula(e.target.value)}
                  required
                  inputMode="numeric"
                  placeholder="5384891"
                  className="tabular"
                />
              </Field>

              <Field label="Nombre y apellido" htmlFor="nombre">
                <Input
                  id="nombre"
                  value={nombre}
                  onChange={(e) => setNombre(e.target.value)}
                  required
                  placeholder="Nombre del paciente"
                />
              </Field>

              <Field label="Especialidad" htmlFor="especialidad">
                <Select
                  id="especialidad"
                  value={specialtyId}
                  onChange={(e) => setSpecialtyId(e.target.value)}
                  required
                  disabled={cargandoEspecialidades}
                >
                  <option value="">
                    {cargandoEspecialidades ? 'Cargando' : 'Seleccionar'}
                  </option>
                  {specialties.map((spec) => (
                    <option key={spec.id} value={spec.id}>
                      {spec.name}
                    </option>
                  ))}
                </Select>
              </Field>

              <Button type="submit" disabled={enviando} className="w-full" size="lg">
                {enviando ? 'Registrando' : 'Registrar turno'}
              </Button>

              {mensaje && <Alert tone={mensaje.tono}>{mensaje.texto}</Alert>}
            </form>
          </CardBody>
        </Card>
      </main>
    </>
  );
}
