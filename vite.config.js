import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',  // old stylesheet, for the Blade pages
                'resources/css/site.css', // new stylesheet, for the Vue pages
                'resources/js/app.js',
                'resources/js/game.js',
            ],
            refresh: true,
            buildDirectory: '../../www/build', // point to www/build

            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        vue(),
    ],
    server: {
        host: '0.0.0.0',
        hmr: {
            host: 'drawmygame.test',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
