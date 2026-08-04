import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            // site.css + the Inertia bootstrap are the whole frontend now. The
            // game engine has no entry of its own: the Game page imports it
            // lazily, so Vite splits it into a chunk loaded only there.
            input: [
                'resources/css/site.css',
                'resources/js/app.js',
            ],
            refresh: true,
            // Builds go to public/build, which is where Laravel's @vite looks by
            // default. This used to be '../../www/build' for the deploy host, but
            // nothing told PHP about it, so the app kept reading a stale
            // public/build and no build reached the browser. If the host really
            // needs the assets elsewhere, set it here *and* call
            // Vite::useBuildDirectory() with the same path so the two agree.

            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        vue({
            template: {
                // Laravel serves /assets/... straight from public/, so absolute
                // URLs in templates must be left for the browser to resolve at
                // runtime — not treated as modules for Vite to bundle.
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
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
