<script setup>
/**
 * The community gallery: every published drawing, each one a link into the
 * play flow.
 *
 * The old Blade card was a div with an onclick that set window.location, so it
 * was never a real link: middle-click, keyboard focus and "open in new tab" all
 * did nothing. Here the whole card is a <Link>, which fixes all three and keeps
 * the navigation inside Inertia.
 */
import { Head, Link } from "@inertiajs/vue3";

import AppLayout from "../Layouts/AppLayout.vue";
import Pagination from "../Components/Pagination.vue";

defineProps({
    // A Laravel paginator: the cards live in drawings.data, the page links in
    // drawings.links.
    drawings: {
        type: Object,
        required: true
    }
});
</script>

<template>
    <Head title="Community" />

    <AppLayout>
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-8 px-6 py-12">

            <header>
                <h1 class="text-2xl font-semibold tracking-tight">Community Levels</h1>
                <p class="mt-2">Play levels created by everyone in the DrawMyGame community.</p>
            </header>

            <p v-if="drawings.data.length === 0" class="flex flex-col items-start gap-4">
                <span>No levels published yet. Be the first!</span>
                <Link href="/upload" class="bg-ink px-4 py-2 text-page">Upload a drawing</Link>
            </p>

            <template v-else>
                <ul class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <li v-for="drawing in drawings.data" :key="drawing.id">
                        <Link
                            :href="`/play/${drawing.id}`"
                            class="flex flex-col gap-3 border border-sub p-3 hover:border-ink"
                        >
                            <!-- The author is named right below, so the alt stays generic. -->
                            <img
                                :src="drawing.image"
                                alt="Level drawing"
                                class="aspect-4/3 w-full object-cover"
                            >

                            <p class="text-sm">By {{ drawing.author }}</p>
                        </Link>
                    </li>
                </ul>

                <Pagination :links="drawings.links" />
            </template>

        </div>
    </AppLayout>
</template>
