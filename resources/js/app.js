/**
 * Inertia bootstrap, loaded by the root view (resources/views/app.blade.php).
 * Every page is an Inertia page, so this is the only JS entry point.
 *
 * The resolve and setup callbacks are written out by hand rather than installing
 * @inertiajs/vite. One less dependency, and it is visible where page components
 * come from.
 */
import { createApp, h } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob("./Pages/**/*.vue");
        return pages[`./Pages/${name}.vue`]();
    },

    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    }
});
