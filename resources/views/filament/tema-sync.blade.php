{{--
    Sincroniza el selector de tema nativo de Filament (claro/oscuro/sistema,
    ver vendor/filament/filament/resources/js/dark-mode.js) con `users.tema`
    — sin esto, Filament solo recuerda el tema en el localStorage de este
    navegador, no "por usuario" (ver TemaController y User::actualizarTema()).
    Los valores de Filament (light/dark/system) no son los mismos que los de
    TemaPreferencia (claro/oscuro/auto), así que se traducen en ambos
    sentidos. Inyectado vía PanelsRenderHook::HEAD_START — corre antes de que
    Alpine dispare "alpine:init" (el momento en que dark-mode.js lee
    localStorage por primera vez), así que la base de datos siempre gana
    sobre un localStorage desactualizado de un dispositivo distinto.
--}}
@auth
    <script>
        (function () {
            var temaGuardado = @json(auth()->user()->tema?->value);
            var mapaAFilament = { claro: 'light', oscuro: 'dark', auto: 'system' };
            var mapaDesdeFilament = { light: 'claro', dark: 'oscuro', system: 'auto' };

            if (temaGuardado && mapaAFilament[temaGuardado]) {
                localStorage.setItem('theme', mapaAFilament[temaGuardado]);
            }

            window.addEventListener('theme-changed', function (event) {
                var temaFilament = event.detail;
                var tema = mapaDesdeFilament[temaFilament];

                if (!tema) {
                    return;
                }

                fetch('{{ route('tema.update') }}', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ tema: tema }),
                }).catch(function () {
                    // Best-effort: si falla, el tema se sigue viendo bien en
                    // este navegador (localStorage ya lo tiene) — solo no
                    // viajará a otro dispositivo hasta el próximo cambio.
                });
            });
        })();
    </script>
@endauth
