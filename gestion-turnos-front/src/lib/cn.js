import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Une clases resolviendo conflictos de Tailwind: la última gana.
 * Permite que un componente traiga estilos por defecto y quien lo usa
 * sobrescriba solo lo que necesita, sin pelear con la especificidad.
 */
export function cn(...clases) {
  return twMerge(clsx(clases));
}
