import { defineConfig } from "vitest/config";

/**
 * Separate config for the tests, kept apart from vite.config.js.
 *
 * Vitest picks up vite.config.js by default, and that pulls in laravel-vite-plugin,
 * which refuses to start once it detects a CI environment:
 *
 *   Error: You should not run the Vite HMR server in CI environments.
 *
 * The tests run on plain ES modules and need neither Laravel, Tailwind nor the font
 * plugin, so a separate config is faster and better isolated as well. A file named
 * vitest.config.js takes precedence over vite.config.js.
 */
export default defineConfig({
    test: {
        environment: "node",
        include: ["resources/js/**/*.test.js"],
    },
});
