import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/authContextValue';
import { Alert, Button, Card, CardBody, Field, Input } from '../components/ui';

// Cada rol entra directo a su pantalla de trabajo.
const DESTINO_POR_ROL = {
  'mesa de entrada': '/reception',
  preconsulta: '/triage',
  profesional: '/doctor',
  admin: '/dashboard',
};

export function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();
  const { login } = useAuth();

  const handleLogin = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      // La sesión queda en una cookie HttpOnly que emite el backend:
      // no se guarda nada en localStorage.
      const user = await login(email, password);

      const rawRole = Array.isArray(user?.roles) ? user.roles[0] : (user?.role ?? '');
      // Un usuario sin roles dejaba rawRole en undefined y reventaba en .toLowerCase()
      const roleName = typeof rawRole === 'object' ? rawRole?.name : rawRole;
      const userRole = (roleName ?? '').toLowerCase();

      navigate(DESTINO_POR_ROL[userRole] ?? '/dashboard', { replace: true });
    } catch (err) {
      console.error('Error en el login:', err);

      if (!err.response) {
        setError('No se pudo establecer conexión con el servidor.');
      } else if (err.response.status === 422 && err.response.data?.errors) {
        const [primerError] = Object.values(err.response.data.errors).flat();
        setError(primerError);
      } else {
        setError(err.response.data?.message ?? 'Credenciales incorrectas.');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <main className="flex min-h-screen items-center justify-center px-6 py-12">
      <div className="w-full max-w-sm">
        <div className="mb-8 text-center">
          <h1 className="text-lg font-semibold">Gestión de Turnos</h1>
          <p className="mt-1 text-sm text-muted">Ingresá con tu cuenta</p>
        </div>

        <Card>
          <CardBody>
            <form onSubmit={handleLogin} className="space-y-4">
              {error && <Alert tone="critical">{error}</Alert>}

              <Field label="Correo electrónico" htmlFor="email">
                <Input
                  id="email"
                  type="email"
                  autoComplete="username"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  required
                  placeholder="nombre@ejemplo.com"
                />
              </Field>

              <Field label="Contraseña" htmlFor="password">
                <Input
                  id="password"
                  type="password"
                  autoComplete="current-password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                  placeholder="••••••••"
                />
              </Field>

              <Button type="submit" disabled={loading} className="w-full" size="lg">
                {loading ? 'Ingresando' : 'Ingresar'}
              </Button>
            </form>
          </CardBody>
        </Card>
      </div>
    </main>
  );
}
