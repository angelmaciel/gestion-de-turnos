import { cn } from '../../lib/cn';

/*
  Primitivas de interfaz.

  Viven en el repositorio (enfoque shadcn/ui) en vez de venir de una
  dependencia cerrada: se pueden ajustar sin esperar a un upstream y sin
  luchar contra estilos que no se ven.
*/

// `hover-fino:` exige puntero real (ver index.css): en tablet el tap dispara
// :hover y lo deja pegado, como si el dedo siguiera sobre el control.
const VARIANTES_BOTON = {
  primary: 'bg-accent text-white hover-fino:hover:bg-accent-hover',
  secondary: 'bg-surface text-ink border border-line-strong hover-fino:hover:bg-canvas',
  ghost: 'text-muted hover-fino:hover:bg-canvas hover-fino:hover:text-ink',
  positive: 'bg-positive text-white hover-fino:hover:brightness-95',
  critical: 'bg-critical text-white hover-fino:hover:brightness-95',
  warning: 'bg-warning text-white hover-fino:hover:brightness-95',
};

const TAMANOS_BOTON = {
  sm: 'h-8 px-3 text-sm',
  md: 'h-10 px-4 text-sm',
  lg: 'h-11 px-6 text-base',
};

/**
 * Indicador de progreso. `aria-hidden` porque quien lo acompaña ya comunica
 * el estado por texto (`aria-busy`, un aviso): duplicarlo sería ruido.
 */
export function Spinner({ className }) {
  return (
    <svg
      className={cn('size-4 animate-spin', className)}
      viewBox="0 0 16 16"
      fill="none"
      aria-hidden="true"
    >
      <circle cx="8" cy="8" r="6" stroke="currentColor" strokeWidth="2" opacity="0.25" />
      <path
        d="M14 8a6 6 0 0 0-6-6"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
      />
    </svg>
  );
}

export function Button({
  variant = 'primary',
  size = 'md',
  className,
  type = 'button',
  loading = false,
  disabled = false,
  children,
  ...props
}) {
  return (
    <button
      type={type}
      // Mientras carga el botón no debe aceptar un segundo click, aunque
      // quien lo usa no haya pasado `disabled`.
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      className={cn(
        'inline-flex items-center justify-center gap-2 rounded-control font-medium',
        // Enumeradas y no `transition-all`, que incluye layout. `scale` va
        // aparte de `transform`: Tailwind 4 usa una propiedad independiente.
        'transition-[background-color,color,border-color,filter,transform,scale]',
        // Bajo 300ms, que es el techo de lo que se siente inmediato.
        'duration-140 ease-salida',
        // El hundido al pulsar sale de `.pulsable` (index.css), unico del
        // sistema: tenerlo tambien acá chocaba a igual especificidad.
        'pulsable',
        'disabled:pointer-events-none disabled:opacity-50',
        VARIANTES_BOTON[variant],
        TAMANOS_BOTON[size],
        className
      )}
      {...props}
    >
      {loading && <Spinner />}
      {children}
    </button>
  );
}

export function Card({ className, ...props }) {
  return (
    <div
      className={cn('rounded-panel border border-line bg-surface', className)}
      {...props}
    />
  );
}

export function CardHeader({ title, description, actions, className }) {
  return (
    <div className={cn('flex items-start justify-between gap-4 border-b border-line px-5 py-4', className)}>
      <div className="min-w-0">
        <h2 className="text-sm font-semibold">{title}</h2>
        {description && <p className="mt-0.5 text-sm text-muted">{description}</p>}
      </div>
      {actions}
    </div>
  );
}

export function CardBody({ className, ...props }) {
  return <div className={cn('p-5', className)} {...props} />;
}

/**
 * Campo con su etiqueta y, si corresponde, el motivo por el que quedó mal.
 *
 * El error toma el id `<htmlFor>-error` para que el control lo referencie con
 * `aria-describedby`; sin eso, un lector de pantalla anuncia el campo sin
 * decir qué tiene de malo.
 */
export function Field({ label, hint, error, htmlFor, children, className }) {
  return (
    <div className={cn('space-y-1.5', className)}>
      <label htmlFor={htmlFor} className="block text-sm font-medium text-ink">
        {label}
      </label>
      {children}
      {/* El error reemplaza a la pista: repetirla compite con el mensaje. */}
      {error ? (
        <p id={htmlFor ? `${htmlFor}-error` : undefined} className="text-xs text-critical">
          {error}
        </p>
      ) : (
        hint && <p className="text-xs text-muted">{hint}</p>
      )}
    </div>
  );
}

/* `text-base` en móvil no es un capricho de escala: Safari en iOS hace zoom
   automático al enfocar un campo de menos de 16px, y al salir deja la página
   corrida. De sm en adelante vuelve a 14px, que es la escala del sistema. */
const ESTILO_CONTROL =
  'w-full rounded-control border border-line-strong bg-surface px-3 text-base text-ink sm:text-sm ' +
  'placeholder:text-subtle disabled:opacity-60 focus:border-accent focus:outline-none ' +
  'focus:ring-2 focus:ring-accent/20';

/* Un campo con error se marca por borde y por `aria-invalid`: el color solo no
   alcanza para quien no lo distingue o no ve la pantalla. */
const ESTILO_CONTROL_INVALIDO =
  'border-critical focus:border-critical focus:ring-critical/20';

export function Input({ className, invalid = false, ...props }) {
  return (
    <input
      aria-invalid={invalid || undefined}
      className={cn(ESTILO_CONTROL, 'h-10', invalid && ESTILO_CONTROL_INVALIDO, className)}
      {...props}
    />
  );
}

export function Select({ className, invalid = false, children, ...props }) {
  return (
    <select
      aria-invalid={invalid || undefined}
      className={cn(ESTILO_CONTROL, 'h-10', invalid && ESTILO_CONTROL_INVALIDO, className)}
      {...props}
    >
      {children}
    </select>
  );
}

const TONOS_BADGE = {
  neutral: 'bg-canvas text-muted border-line',
  accent: 'bg-accent-soft text-accent border-accent/20',
  positive: 'bg-positive-soft text-positive border-positive/20',
  warning: 'bg-warning-soft text-warning border-warning/25',
  critical: 'bg-critical-soft text-critical border-critical/20',
};

export function Badge({ tone = 'neutral', className, ...props }) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium',
        TONOS_BADGE[tone],
        className
      )}
      {...props}
    />
  );
}

/**
 * Aviso de resultado de una acción. El tono comunica, no un emoji.
 *
 * Un aviso crítico se anuncia con `role="alert"` (interrumpe al lector de
 * pantalla) y el resto con `role="status"` (espera a que termine de leer):
 * un error de login que nadie anuncia deja al usuario esperando sin saber
 * que la acción falló.
 */
export function Alert({ tone = 'neutral', className, children, ...props }) {
  return (
    <div
      role={tone === 'critical' ? 'alert' : 'status'}
      className={cn(
        'rounded-control border px-4 py-3 text-sm',
        TONOS_BADGE[tone],
        className
      )}
      {...props}
    >
      {children}
    </div>
  );
}

/** Estado vacío: explica qué falta en vez de dejar un hueco. */
export function EmptyState({ children, className }) {
  return (
    <p className={cn('py-8 text-center text-sm text-subtle', className)}>{children}</p>
  );
}

/** Par etiqueta/valor para fichas de datos. */
export function DataPoint({ label, value, tone }) {
  return (
    <div className="min-w-0">
      <dt className="text-xs font-medium tracking-wide text-muted uppercase">{label}</dt>
      <dd className={cn('mt-0.5 text-sm font-semibold tabular', tone)}>{value}</dd>
    </div>
  );
}

export function PageHeader({ title, description, actions }) {
  return (
    <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 className="text-xl font-semibold">{title}</h1>
        {description && <p className="mt-1 text-sm text-muted">{description}</p>}
      </div>
      {actions}
    </div>
  );
}
