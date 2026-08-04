<script setup>
/**
 * The shell every migrated page sits in: a top bar with the nav and the account
 * chip, and a slim footer.
 *
 * The nav links are plain <a> elements, not Inertia <Link>s, and have to stay
 * that way until the pages they point at are migrated. A <Link> fetches its
 * target over XHR and expects an Inertia response; Home, About, Upload and
 * Community are still Blade, so that request would come back as plain HTML and
 * Inertia would throw. A plain <a> is a normal browser navigation and just works.
 */
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";

const NAV_LINKS = [
    { label: "Home", href: "/" },
    { label: "About", href: "/about" },
    { label: "Upload", href: "/upload" },
    { label: "Community", href: "/community" }
];

// Shared from HandleInertiaRequests::share(), so every page has it without
// having to pass it through as a prop.
const user = computed(() => usePage().props.auth.user);

const year = new Date().getFullYear();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-page text-ink">

        <header class="border-b border-sub">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-8 px-6 py-4">

                <a href="/" class="text-lg font-semibold tracking-tight">
                    DrawMyGame
                </a>

                <nav class="flex items-center gap-6 text-sm">
                    <a
                        v-for="link in NAV_LINKS"
                        :key="link.href"
                        :href="link.href"
                        class="hover:underline"
                    >
                        {{ link.label }}
                    </a>

                    <a
                        :href="user ? '/account' : '/login'"
                        class="flex size-9 items-center justify-center rounded-full bg-sub text-xs font-semibold"
                        :title="user ? user.username : 'Log in'"
                    >
                        {{ user ? user.initials : "?" }}
                    </a>
                </nav>

            </div>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <footer class="border-t border-sub">
            <div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-5 text-sm">

                <p>&copy; {{ year }} DrawMyGame</p>

                <nav class="flex gap-6">
                    <a
                        v-for="link in NAV_LINKS"
                        :key="link.href"
                        :href="link.href"
                        class="hover:underline"
                    >
                        {{ link.label }}
                    </a>
                </nav>

            </div>
        </footer>

    </div>
</template>
