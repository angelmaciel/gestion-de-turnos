import { useEffect, useRef, useState } from 'react';
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
  // Marcar los campos en rojo solo tiene sentido cuando el problema son los
  // datos escritos. Un 500 o una caída de red no son culpa de lo que tipeó el
  // usuario, y señalarlos lo manda a corregir algo que estaba bien.
  const [errorEnCampos, setErrorEnCampos] = useState(false);
  // Contador y no booleano: dos fallos seguidos tienen que devolver el foco
  // las dos veces, y un booleano que ya estaba en true no dispara el efecto.
  const [intentosFallidos, setIntentosFallidos] = useState(0);
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
    setErrorEnCampos(false);
  };

  /*
    El foco vuelve a la contraseña con el texto seleccionado: reintentar es
    escribir de nuevo, sin pasar por el mouse.

    Va en un efecto y no en el `catch` porque ahí el campo todavía está dentro
    del `<fieldset disabled>` —`setLoading(false)` corre después, en el
    `finally`— y `focus()` sobre un control deshabilitado no hace nada: el
    foco terminaba en `<body>`, justo lo contrario de lo buscado. El efecto
    espera a que el formulario vuelva a estar habilitado.
  */
  useEffect(() => {
    if (intentosFallidos === 0 || loading) return;

    passwordRef.current?.focus();
    passwordRef.current?.select();
  }, [intentosFallidos, loading]);

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
        setErrorEnCampos(false);
      } else if (status === 422 && err.response.data?.errors) {
        const [primerError] = Object.values(err.response.data.errors).flat();
        setError(primerError);
        setErrorEnCampos(true);
      } else if (status >= 500) {
        // Un 5xx trae respuesta, así que antes se colaba por el `else` y
        // acusaba al usuario de escribir mal una contraseña que estaba bien.
        setError('El servidor no está respondiendo. Probá de nuevo en unos minutos.');
        setErrorEnCampos(false);
      } else if (status === 419) {
        setError('La sesión expiró. Recargá la página e intentá otra vez.');
        setErrorEnCampos(false);
      } else if (status === 429) {
        setError('Demasiados intentos. Esperá un momento antes de reintentar.');
        setErrorEnCampos(false);
      } else {
        setError(err.response.data?.message ?? 'Credenciales incorrectas.');
        setErrorEnCampos(true);
      }

      setIntentosFallidos((n) => n + 1);
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

          {/* Vidrio real: el blanco al 10% reemplaza al sólido del componente
              y queda apenas una veladura, así la foto se ve atravesar el card
              en vez de esconderse detrás. El desenfoque es lo que la vuelve
              difusa adentro y nítida afuera.

              Sin borde: la sombra proyectada es lo único que separa el card
              del fondo, así que se mantiene marcada.

              El radio va en `.card-vidrio` y no como utilidad: tailwind-merge
              no sabe que `rounded-panel` es un radio, así que no lo desplaza y
              gana el que la hoja emite último. */}
          <Card
            className="card-vidrio entra border-0 bg-white/10 shadow-2xl shadow-black/50 backdrop-blur-2xl"
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
                      invalid={errorEnCampos}
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
                        invalid={errorEnCampos}
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
