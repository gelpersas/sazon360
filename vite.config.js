import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/filament/admin/theme.css', 'resources/js/app.js', 'resources/js/pos/main.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        vue(),
        VitePWA({
            registerType: 'autoUpdate',
            injectRegister: 'auto',
            manifest: {
                name: 'Sazón360 POS',
                short_name: 'Sazón360',
                start_url: '/pos',
                display: 'standalone',
                // Paleta "Moka Contraste" (ver docs/DECISIONES.md DEC-070) —
                // antes quedaban en un azul marino sin relación con ninguna
                // paleta del POS (leftover sin actualizar desde el scaffold
                // inicial). background_color es el fondo del splash screen
                // al abrir la PWA instalada; theme_color tiñe la barra de
                // estado/título del SO — ambos ahora coherentes con
                // pos.css (--color-bg / --color-primary).
                background_color: '#ece7dd',
                theme_color: '#d9641f',
            },
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
