# Reglas de seguridad — Sazón360

> Sin `paths:` a propósito: esta regla se carga en toda sesión, independientemente del archivo que se esté tocando, por su carácter transversal y crítico (aislamiento multiempresa, autorización). El repositorio aún no tiene código (ver `docs/DECISIONES.md` DEC-000); estas reglas aplican desde el primer módulo que se construya.

- Autorización siempre en el backend (Policies/Form Requests/Middleware) — el frontend (Filament o Vue) solo mejora la UX, nunca es el único guardián.
- Prevención de acceso cruzado entre empresas: todo query que toque datos operativos debe estar scopeado por `empresa_id` (y `sede_id` cuando aplique) del usuario autenticado, nunca por un valor recibido del cliente sin validar contra su acceso real.
- Protección de secretos: nunca hardcodear credenciales, tokens o claves en código o migraciones; todo va en `.env` (que no se lee ni edita desde aquí — ver `CLAUDE.md`).
- Validación de entradas: toda entrada de usuario (formularios, API, importaciones) se valida antes de usarse, incluyendo tipo, rango y pertenencia (ej. que un `producto_id` recibido realmente pertenezca a la empresa del usuario).
- Rate limiting en endpoints sensibles o de alto volumen (login, creación de pedidos desde POS) cuando el framework lo permita de forma simple.
- Auditoría de acciones críticas: anulaciones, descuentos, reaperturas, cambios de precio, movimientos de caja/inventario deben quedar registrados con usuario y momento.
- No registrar datos sensibles innecesariamente en logs (contraseñas, tokens, datos completos de tarjetas si en algún momento se procesan pagos con tarjeta).
- Prevención de asignación masiva insegura: `$fillable`/`$guarded` explícitos en modelos Eloquent, nunca exponer campos como `empresa_id` o `rol` a asignación masiva desde datos del request.
- Revisión de dependencias: no agregues paquetes de Composer/npm sin justificar por qué son necesarios (ver regla de "no agregar dependencias sin explicar su necesidad" en `CLAUDE.md`).
- Principio de mínimo privilegio: un rol/usuario solo debe poder hacer lo que su función requiere; evita permisos amplios "por si acaso".
