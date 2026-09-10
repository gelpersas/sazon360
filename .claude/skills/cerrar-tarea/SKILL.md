---
name: cerrar-tarea
description: Cerrar formalmente una tarea de Sazón360 revisando cambios, ejecutando pruebas y formateadores, y actualizando la documentación (MODULO-ACTUAL.md, DECISIONES.md si aplica). No hace git push ni crea commits sin autorización. Úsalo al terminar una tarea o sesión de trabajo.
---

# /cerrar-tarea

1. Revisa los cambios realizados en la sesión (diff, archivos tocados).
2. Ejecuta las pruebas relacionadas con lo modificado (no toda la suite salvo que el cambio sea amplio).
3. Ejecuta los formateadores/linters correspondientes al código tocado (PHP y/o JS/Vue, según lo que exista configurado en el proyecto).
4. Muestra qué pruebas se ejecutaron y su resultado.
5. Actualiza `docs/MODULO-ACTUAL.md` con: trabajo terminado, trabajo pendiente, pruebas ejecutadas, errores conocidos, decisiones pendientes, próxima acción exacta.
6. Actualiza `docs/DECISIONES.md` si la tarea tomó o resolvió alguna decisión arquitectónica.
7. Comprueba que no se haya incluido ninguna función fuera del alcance acordado para la tarea.
8. Prepara un resumen breve listo para usar como mensaje de commit (qué cambió y por qué, no el detalle de cada línea).
9. No ejecutes `git push`.
10. No crees commits automáticamente — solo si el usuario lo autoriza explícitamente en esta sesión.
