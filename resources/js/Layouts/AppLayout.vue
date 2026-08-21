<script setup>
/**
 * The shell every page sits in: a top bar with the nav and the account chip,
 * and a slim footer.
 *
 * Now that every page is an Inertia page, the nav uses <Link> throughout: a
 * Link fetches its target over XHR and swaps the page component in place, so
 * navigation keeps the app state and skips the full reload. Only off-site
 * destinations (like /auth/google on the login page) still need a plain <a>.
 */
import { computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";

import CookieNotice from "../Components/CookieNotice.vue";
import FlashToast from "../Components/FlashToast.vue";

const NAV_LINKS = [
    { label: "Home", href: "/" },
    { label: "About", href: "/about" },
    { label: "Upload", href: "/upload" },
    { label: "Draw", href: "/draw" },
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

                <Link href="/" class="text-lg font-semibold tracking-tight">
                    DrawMyGame
                </Link>

                <nav class="flex items-center gap-6 text-sm">
                    <Link
                        v-for="link in NAV_LINKS"
                        :key="link.href"
                        :href="link.href"
                        class="hover:underline"
                    >
                        {{ link.label }}
                    </Link>

                    <!-- Logging out lives on the account page, with the rest of
                         what you can do to your account. -->
                    <Link
                        v-if="user"
                        href="/account"
                        class="flex size-9 items-center justify-center rounded-full bg-sub text-xs font-semibold"
                        :title="user.username"
                    >
                        {{ user.initials }}
                    </Link>

                    <Link v-else href="/login" class="hover:underline">
                        Log in
                    </Link>
                </nav>

            </div>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <footer class="border-t border-sub">
            <div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-5 text-sm">

                <p>&copy; {{ year }} DrawMyGame</p>

                <nav class="flex flex-wrap gap-6">
                    <Link
                        v-for="link in NAV_LINKS"
                        :key="link.href"
                        :href="link.href"
                        class="hover:underline"
                    >
                        {{ link.label }}
                    </Link>

                    <!-- Footer only, so it is deliberately not in NAV_LINKS:
                         that array feeds the top nav as well, and this does not
                         belong up there. -->
                    <Link href="/cookies" class="hover:underline">
                        Cookies
                    </Link>
                </nav>

            </div>
        </footer>

        <FlashToast />

        <CookieNotice />

    </div>
</template>
