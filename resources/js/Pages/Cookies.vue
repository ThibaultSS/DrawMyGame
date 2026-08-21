<script setup>
/**
 * What this site stores, in full.
 *
 * The list below is the whole of it. If a cookie is ever added — analytics, or
 * anything else that is not strictly necessary — it belongs here first, and it
 * would need asking permission rather than merely saying so.
 */
import { Head, Link } from "@inertiajs/vue3";

import AppLayout from "../Layouts/AppLayout.vue";

const STORED = [
    {
        name: "drawmygame-session",
        what: "A cookie",
        why: "Keeps you signed in, and remembers which colours you picked for the level you are playing.",
        howLong: "Two hours after you stop using the site."
    },
    {
        name: "XSRF-TOKEN",
        what: "A cookie",
        why: "Proves that a form was really submitted from this site, which is what stops another site submitting one on your behalf.",
        howLong: "Until you close the browser."
    },
    {
        name: "The level store",
        what: "Browser storage, not a cookie",
        why: "Holds the drawing you are playing inside your own browser. It is never sent anywhere unless you press Save, which is why an unsaved level has no address and cannot be shared.",
        howLong: "Until you save the level or clear your browser data."
    }
];
</script>

<template>
    <Head title="Cookies" />

    <AppLayout>
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-8 px-6 py-16">

            <header>
                <h1 class="text-2xl font-semibold tracking-tight">Cookies</h1>

                <p class="mt-4">
                    DrawMyGame stores three things, and needs all three to work at
                    all. There is no analytics here, no advertising, and no
                    third-party tracker of any kind — nothing about you is
                    profiled, sold or shared.
                </p>
            </header>

            <ul class="flex flex-col gap-6">
                <li
                    v-for="item in STORED"
                    :key="item.name"
                    class="flex flex-col gap-2 border border-sub p-6"
                >
                    <p class="font-medium">{{ item.name }}</p>

                    <p class="text-sm">{{ item.what }}</p>

                    <p>{{ item.why }}</p>

                    <p class="text-sm">Kept: {{ item.howLong }}</p>
                </li>
            </ul>

            <section>
                <h2 class="text-2xl font-semibold tracking-tight">Signing in with Google</h2>

                <p class="mt-4">
                    Choosing to sign in with Google sends you to Google, and Google
                    sets its own cookies while you are there. Those belong to
                    Google's website rather than this one, and this site never sees
                    them. All that comes back here is confirmation of who you are.
                </p>
            </section>

            <section>
                <h2 class="text-2xl font-semibold tracking-tight">Getting rid of them</h2>

                <p class="mt-4">
                    Every browser can clear cookies and site data, usually under
                    privacy settings. Clearing them signs you out and forgets any
                    level you had not saved, but nothing you did save is affected —
                    those live on your account, and you can delete them yourself
                    from the account page at any time.
                </p>

                <Link href="/account" class="mt-6 inline-block bg-ink px-4 py-2 text-page">
                    Go to your account
                </Link>
            </section>

        </div>
    </AppLayout>
</template>
