import { useNavigate } from 'react-router-dom';
import { Navbar } from '../components/Navbar';
import { useAuth } from '../context/authContextValue';
import { Button, Card, CardBody, PageHeader } from '../components/ui';

const MODULOS = [
  {
    // Se llama igual que el rol y que el enlace del navbar. Este quedó como
    // "Recepción" cuando se unificó el nombre en el resto del sistema.
    titulo: 'Mesa de entrada',
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

// Solo para admin: el resto de MODULOS no filtra por rol hoy (se ve igual
// para todos), pero esta tarjeta lleva a una pantalla que da 403 sin ese rol.
const TORRE_DE_CONTROL = {
  titulo: 'Torre de Control',
  descripcion: 'Estado en vivo de las salas: detectá el cuello de botella de un vistazo.',
  accion: 'Abrir',
  ruta: '/admin/torre-control',
};

export function Dashboard() {
  const navigate = useNavigate();
  const { roles } = useAuth();
  const modulos = roles.includes('admin') ? [...MODULOS, TORRE_DE_CONTROL] : MODULOS;

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
      {/* Sin foto de fondo: era la única pantalla interna que tenía una, y
          encima detrás de un velo al 50% que la lavaba a la mitad. Queda sobre
          el lienzo del sistema, igual que las demás. */}
      <main className="mx-auto max-w-4xl px-6 py-8">
        <PageHeader title="Módulos" description="Elegí la pantalla según la tarea." />

        <div className="grid gap-4 sm:grid-cols-2">
          {modulos.map((modulo) => (
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
