import { useNavigate, Link, useLocation } from 'react-router-dom';
import { useAuth } from '../context/authContextValue';
import { Button } from './ui';

// Cada rol solo ve los accesos que puede usar; el admin los ve todos.
const ENLACES = [
  // El label sigue al nombre del rol, que es como se llama el puesto adentro
  // del centro. La ruta queda en /reception por compatibilidad con los enlaces
  // ya repartidos.
  { to: '/reception', label: 'Mesa de entrada', roles: ['mesa de entrada'] },
  { to: '/triage', label: 'Preconsulta', roles: ['preconsulta'] },
  { to: '/doctor', label: 'Consultorio', roles: ['profesional'] },
  { to: '/admin/torre-control', label: 'Torre de Control', roles: ['admin'] },
  // La pantalla de sala es el televisor de la espera: la despliega el
  // profesional, que es quien canta el turno, o el admin al montar el puesto.
  // Mesa de entrada y preconsulta no la necesitan.
  { to: '/tv', label: 'Pantalla de sala', roles: ['profesional', 'admin'], externo: true },
];

export function Navbar() {
  const navigate = useNavigate();
  const location = useLocation();

  // El usuario viene del contexto, que lo pide a /auth/me: nada en localStorage.
  const { user, roles, logout } = useAuth();
  const esAdmin = roles.includes('admin');

  const handleLogout = async () => {
    await logout();
    navigate('/login', { replace: true });
  };

  const visibles = ENLACES.filter((e) => esAdmin || e.roles.some((r) => roles.includes(r)));
  const inicio = esAdmin ? '/dashboard' : (visibles[0]?.to ?? '/dashboard');

  return (
    <header className="border-b border-line bg-surface">
      <nav className="mx-auto flex h-14 max-w-6xl items-center gap-6 px-6">
        <Link to={inicio} className="text-sm font-semibold tracking-tight whitespace-nowrap">
          Gestión de Turnos
        </Link>

        <div className="flex flex-1 items-center gap-1">
          {visibles.map((enlace) => {
            const activo = location.pathname === enlace.to;

            return (
              <Link
                key={enlace.to}
                to={enlace.to}
                // La pantalla de sala va en otra pestaña: se deja abierta en el
                // televisor y quien la abrió sigue trabajando en la suya.
                target={enlace.externo ? '_blank' : undefined}
                aria-current={activo ? 'page' : undefined}
                className={
                  activo
                    ? 'rounded-control bg-accent-soft px-3 py-1.5 text-sm font-medium text-accent'
                    : 'rounded-control px-3 py-1.5 text-sm text-muted transition-colors hover:bg-canvas hover:text-ink'
                }
              >
                {enlace.label}
              </Link>
            );
          })}
        </div>

        <div className="flex items-center gap-4">
          {/* Quién está usando el sistema */}
          <div className="hidden text-right leading-tight sm:block">
            <p className="text-sm font-medium">{user?.name ?? 'Sin identificar'}</p>
            <p className="text-xs text-muted">
              {roles.length > 0 ? roles.join(', ') : 'sin rol'}
            </p>
          </div>

          <Button variant="secondary" size="sm" onClick={handleLogout}>
            Salir
          </Button>
        </div>
      </nav>
    </header>
  );
}
