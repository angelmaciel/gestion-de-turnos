import { useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/authContextValue';
import { Alert, Button, Card, CardBody, Field, Input } from '../components/ui';
import fondoLogin from '../assets/abdulai-sayni-unsplash.jpg';

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
  const [verPassword, setVerPassword] = useState(false);
  const [bloqMayus, setBloqMayus] = useState(false);
  const passwordRef = useRef(null);
  const navigate = useNavigate();
  const { login } = useAuth();

  // Bloq Mayús activado es la causa silenciosa más común de "credenciales
  // incorrectas": el campo está enmascarado y no se ve qué se escribió.
  const revisarBloqMayus = (e) => setBloqMayus(e.getModifierState?.('CapsLock') ?? false);

  // Un error viejo al lado de un campo que ya se está corrigiendo miente
  // sobre el estado actual del formulario.
  const editar = (setter) => (e) => {
    setter(e.target.value);
    setError('');
  };

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

      const status = err.response?.status;

      if (!err.response) {
        setError('No se pudo establecer conexión con el servidor.');
      } else if (status === 422 && err.response.data?.errors) {
        const [primerError] = Object.values(err.response.data.errors).flat();
        setError(primerError);
      } else if (status >= 500) {
        // Un 5xx trae respuesta, así que antes se colaba por el `else` y
        // acusaba al usuario de escribir mal una contraseña que estaba bien.
        setError('El servidor no está respondiendo. Probá de nuevo en unos minutos.');
      } else if (status === 419) {
        setError('La sesión expiró. Recargá la página e intentá otra vez.');
      } else if (status === 429) {
        setError('Demasiados intentos. Esperá un momento antes de reintentar.');
      } else {
        setError(err.response.data?.message ?? 'Credenciales incorrectas.');
      }

      // El foco vuelve a la contraseña con el texto seleccionado: reintentar
      // es escribir de nuevo, sin pasar por el mouse.
      passwordRef.current?.focus();
      passwordRef.current?.select();
    } finally {
      setLoading(false);
    }
  };

  return (
    <div
      className="min-h-screen bg-cover bg-center bg-no-repeat"
      style={{ backgroundImage: `url(${fondoLogin})` }}
    >
      {/* Velo oscuro en vez del claro que había: la foto tiene zonas de tela
          bastante luminosas y el texto blanco se perdía sobre ellas. Al 50%
          la imagen se sigue leyendo entera y el contraste deja de depender de
          qué parte quede detrás. */}
      <main className="flex min-h-screen items-center justify-center bg-black/50 px-5 py-12 sm:px-6">
        <div className="w-full max-w-sm">
          <div className="entra mb-8 text-center">
            <h1 className="titulo-entrada text-white">Gestión de Turnos</h1>
            <p className="mt-2 text-sm text-white/85">Ingresá con tu cuenta</p>
          </div>

          {/* Vidrio real: `bg-transparent` desactiva el blanco sólido del
              componente y queda apenas un 10% de veladura, así la foto se ve
              atravesar el card en vez de esconderse detrás. El desenfoque es
              lo que la vuelve difusa adentro y nítida afuera.

              Sin borde: la sombra proyectada es lo único que separa el card
              del fondo, así que se mantiene marcada. */}
          <Card
            className="card-vidrio entra rounded-3xl border-0 bg-transparent bg-white/10 shadow-2xl shadow-black/50 backdrop-blur-2xl"
            style={{ animationDelay: '260ms' }}
          >
            <CardBody>
              <form onSubmit={handleLogin} className="space-y-4">
                {error && <Alert tone="critical">{error}</Alert>}

                {/* Durante el envío el formulario entero queda inerte: editar
                    un campo cuya petición ya salió no cambia el resultado. */}
                <fieldset disabled={loading} className="space-y-4">
                  <Field label="Correo electrónico" htmlFor="email">
                    <Input
                      id="email"
                      type="email"
                      autoComplete="username"
                      value={email}
                      onChange={editar(setEmail)}
                      required
                      autoFocus
                      invalid={Boolean(error)}
                    />
                  </Field>

                  <Field label="Contraseña" htmlFor="password">
                    <div className="relative">
                      <Input
                        id="password"
                        ref={passwordRef}
                        type={verPassword ? 'text' : 'password'}
                        autoComplete="current-password"
                        value={password}
                        onChange={editar(setPassword)}
                        onKeyUp={revisarBloqMayus}
                        onBlur={() => setBloqMayus(false)}
                        required
                        invalid={Boolean(error)}
                        aria-describedby={bloqMayus ? 'aviso-mayus' : undefined}
                        className="pr-20"
                      />
                      <button
                        type="button"
                        onClick={() => setVerPassword((v) => !v)}
                        aria-pressed={verPassword}
                        className="absolute inset-y-0 right-0 rounded-control px-3 text-xs font-medium text-white hover:text-white/75"
                      >
                        {verPassword ? 'Ocultar' : 'Mostrar'}
                      </button>
                    </div>
                    {bloqMayus && (
                      <p id="aviso-mayus" role="status" className="aviso-mayus text-xs">
                        Bloq Mayús está activado.
                      </p>
                    )}
                  </Field>
                </fieldset>

                <Button type="submit" loading={loading} className="w-full" size="lg">
                  {loading ? 'Ingresando…' : 'Ingresar'}
                </Button>
              </form>
            </CardBody>
          </Card>
        </div>
      </main>
    </div>
  );
}
