/**
 * Clave de idempotencia para operaciones que no deben duplicarse si el
 * cliente reintenta por un corte de red (ver docs/DECISIONES.md DEC-009,
 * riesgo de Fase 4 en docs/ROADMAP.md). Se genera una vez por intento del
 * usuario y se reutiliza en los reintentos automáticos de esa misma acción.
 */
export function generarIdempotencyKey() {
  if (window.crypto?.randomUUID) {
    return window.crypto.randomUUID();
  }

  return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
}
