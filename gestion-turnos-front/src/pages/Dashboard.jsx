import { useNavigate } from 'react-router-dom';
import { Navbar } from '../components/Navbar';
import { Button, Card, CardBody, PageHeader } from '../components/ui';

const MODULOS = [
  {
    titulo: 'Recepción',
    descripcion: 'Registrar pacientes y emitir turnos.',
    accion: 'Abrir',
    ruta: '/reception',
  },
  {
    titulo: 'Preconsulta',
    descripcion: 'Tomar peso, altura y presión antes de la consulta.',
    accion: 'Abrir',
    ruta: '/triage',
  },
  {
    titulo: 'Consultorio',
    descripcion: 'Ver la cola de pacientes y llamar al siguiente.',
    accion: 'Abrir',
    ruta: '/doctor',
  },
  {
    titulo: 'Pantalla de sala',
    descripcion: 'Vista pública para el televisor de la sala de espera.',
    accion: 'Abrir en pestaña nueva',
    ruta: '/tv',
    externa: true,
  },
];

export function Dashboard() {
  const navigate = useNavigate();

  const abrir = (modulo) => {
    if (modulo.externa) {
      window.open(modulo.ruta, '_blank', 'noopener');

      return;
    }

    navigate(modulo.ruta);
  };

  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-4xl px-6 py-8">
        <PageHeader title="Módulos" description="Elegí la pantalla según la tarea." />

        <div className="grid gap-4 sm:grid-cols-2">
          {MODULOS.map((modulo) => (
            <Card key={modulo.ruta}>
              <CardBody className="flex h-full flex-col">
                <h2 className="text-sm font-semibold">{modulo.titulo}</h2>
                <p className="mt-1 mb-5 flex-1 text-sm text-muted">{modulo.descripcion}</p>
                <Button variant="secondary" onClick={() => abrir(modulo)} className="w-full">
                  {modulo.accion}
                </Button>
              </CardBody>
            </Card>
          ))}
        </div>
      </main>
    </>
  );
}
