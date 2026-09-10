---
name: investigar-error
description: Investigar un bug o error reportado en Sazón360, reduciéndolo a su causa raíz y proponiendo la corrección mínima, con una prueba que lo reproduzca. Úsalo cuando el usuario reporte un error o comportamiento inesperado, no para construir features nuevas.
---

# /investigar-error

1. Reproduce el problema (pasos exactos, o pide al usuario los pasos/datos si faltan).
2. Obtén evidencia: mensaje de error, stack trace, logs relevantes (resumidos, no el log completo si es extenso).
3. Reduce el problema al componente responsable (¿backend, frontend, migración, configuración?).
4. Revisa logs de forma focalizada (por fecha/módulo relevante), no logs completos sin filtrar.
5. Identifica la causa raíz — no te quedes en el síntoma.
6. Propón la corrección mínima que resuelve la causa raíz.
7. No reescribas módulos completos para corregir un bug puntual.
8. Crea una prueba (Pest) que reproduzca el error antes de corregirlo.
9. Aplica la solución solo si el usuario lo pidió explícitamente (si solo pidió investigar, entrega el diagnóstico y espera confirmación).
10. Confirma que la corrección no produjo regresiones ejecutando las pruebas relacionadas con el área afectada.
