import { cn } from '../../lib/cn';

/*
  Primitivas de interfaz.

  Viven en el repositorio (enfoque shadcn/ui) en vez de venir de una
  dependencia cerrada: se pueden ajustar sin esperar a un upstream y sin
  luchar contra estilos que no se ven.
*/

const VARIANTES_BOTON = {
  primary: 'bg-accent text-white hover:bg-accent-hover',
  secondary: 'bg-surface text-ink border border-line-strong hover:bg-canvas',
  ghost: 'text-muted hover:bg-canvas hover:text-ink',
  positive: 'bg-positive text-white hover:brightness-95',
  critical: 'bg-critical text-white hover:brightness-95',
  warning: 'bg-warning text-white hover:brightness-95',
};

const TAMANOS_BOTON = {
  sm: 'h-8 px-3 text-sm',
  md: 'h-10 px-4 text-sm',
  lg: 'h-11 px-6 text-base',
};

export function Button({
  variant = 'primary',
  size = 'md',
  className,
  type = 'button',
  ...props
}) {
  return (
    <button
      type={type}
      className={cn(
        'inline-flex items-center justify-center gap-2 rounded-control font-medium',
        'transition-colors disabled:pointer-events-none disabled:opacity-50',
        VARIANTES_BOTON[variant],
        TAMANOS_BOTON[size],
        className
      )}
      {...props}
    />
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

export function Field({ label, hint, htmlFor, children, className }) {
  return (
    <div className={cn('space-y-1.5', className)}>
      <label htmlFor={htmlFor} className="block text-sm font-medium text-ink">
        {label}
      </label>
      {children}
      {hint && <p className="text-xs text-muted">{hint}</p>}
    </div>
  );
}

const ESTILO_CONTROL =
  'w-full rounded-control border border-line-strong bg-surface px-3 text-sm text-ink ' +
  'placeholder:text-subtle disabled:opacity-60 focus:border-accent focus:outline-none ' +
  'focus:ring-2 focus:ring-accent/20';

export function Input({ className, ...props }) {
  return <input className={cn(ESTILO_CONTROL, 'h-10', className)} {...props} />;
}

export function Select({ className, children, ...props }) {
  return (
    <select className={cn(ESTILO_CONTROL, 'h-10', className)} {...props}>
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

/** Aviso de resultado de una acción. El tono comunica, no un emoji. */
export function Alert({ tone = 'neutral', className, children }) {
  return (
    <div
      role="status"
      className={cn(
        'rounded-control border px-4 py-3 text-sm',
        TONOS_BADGE[tone],
        className
      )}
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
