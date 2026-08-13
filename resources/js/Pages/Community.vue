<script setup>
/**
 * The community gallery: every published drawing, each one a link into the
 * play flow.
 *
 * The old Blade card was a div with an onclick that set window.location, so it
 * was never a real link: middle-click, keyboard focus and "open in new tab" all
 * did nothing. Here the whole card is a <Link>, which fixes all three and keeps
 * the navigation inside Inertia.
 *
 * Cards now carry what a level is (title, description) and how it stands
 * (likes, dislikes), and the gallery can be searched and reordered — without
 * that, the newest levels bury everything else and a good one is never seen
 * again once it falls off the first page.
 */
import { onUnmounted, ref, watch } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";

import AppLayout from "../Layouts/AppLayout.vue";
import Pagination from "../Components/Pagination.vue";

const props = defineProps({
    // A Laravel paginator: the cards live in drawings.data, the page links in
    // drawings.links.
    drawings: {
        type: Object,
        required: true
    },
    // What the server searched and sorted by, so a reload or a shared URL comes
    // back with the controls where they were.
    filters: {
        type: Object,
        required: true
    }
});

const search = ref(props.filters.search);
const sort = ref(props.filters.sort);

// Typing should not fire a request per keystroke.
const DEBOUNCE_MS = 300;

let debounce = null;

watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, DEBOUNCE_MS);
});

onUnmounted(() => clearTimeout(debounce));

function setSort(value) {
    if (sort.value === value) {
        return;
    }

    sort.value = value;
    applyFilters();
}

function applyFilters() {
    router.get(
        "/community",
        {
            // Defaults are left out rather than spelled out, so the plain
            // gallery keeps a clean /community URL.
            search: search.value || undefined,
            sort: sort.value === "newest" ? undefined : sort.value
        },
        {
            // preserveState keeps the box you are typing in from being replaced
            // underneath you; replace keeps every keystroke out of the history.
            preserveState: true,
            preserveScroll: true,
            replace: true
        }
    );
}
</script>

<template>
    <Head title="Community" />

    <AppLayout>
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-8 px-6 py-12">

            <header>
                <h1 class="text-2xl font-semibold tracking-tight">Community Levels</h1>
                <p class="mt-2">Play levels created by everyone in the DrawMyGame community.</p>
            </header>

            <div class="flex flex-wrap items-center justify-between gap-4">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search by title or author"
                    aria-label="Search by title or author"
                    class="w-full max-w-xs border border-sub px-3 py-2 outline-none focus:border-ink"
                >

                <div class="flex gap-2 text-sm">
                    <button
                        type="button"
                        class="px-3 py-1.5"
                        :class="sort === 'newest' ? 'bg-ink text-page' : 'border border-sub'"
                        :aria-pressed="sort === 'newest'"
                        @click="setSort('newest')"
                    >
                        Newest
                    </button>

                    <button
                        type="button"
                        class="px-3 py-1.5"
                        :class="sort === 'liked' ? 'bg-ink text-page' : 'border border-sub'"
                        :aria-pressed="sort === 'liked'"
                        @click="setSort('liked')"
                    >
                        Most liked
                    </button>
                </div>
            </div>

            <!-- Nothing found and nothing published read the same on screen but
                 mean different things, so they say different things. -->
            <p v-if="drawings.data.length === 0 && filters.search">
                No levels match “{{ filters.search }}”.
            </p>

            <p v-else-if="drawings.data.length === 0" class="flex flex-col items-start gap-4">
                <span>No levels published yet. Be the first!</span>
                <Link href="/upload" class="bg-ink px-4 py-2 text-page">Upload a drawing</Link>
            </p>

            <template v-else>
                <ul class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <li v-for="drawing in drawings.data" :key="drawing.id">
                        <Link
                            :href="`/play/${drawing.id}`"
                            class="flex h-full flex-col gap-3 border border-sub p-3 hover:border-ink"
                        >
                            <!-- The title is right below, so the alt stays generic. -->
                            <img
                                :src="drawing.image"
                                alt="Level drawing"
                                class="aspect-4/3 w-full object-cover"
                            >

                            <p class="font-medium">{{ drawing.title || "Untitled" }}</p>

                            <p v-if="drawing.description" class="line-clamp-2 text-sm">
                                {{ drawing.description }}
                            </p>

                            <!-- mt-auto pins this to the bottom, so cards with
                                 no description still line their footers up. -->
                            <p class="mt-auto flex justify-between gap-3 text-sm">
                                <span>By {{ drawing.author }}</span>
                                <span>{{ drawing.likes }} likes · {{ drawing.dislikes }} dislikes</span>
                            </p>
                        </Link>
                    </li>
                </ul>

                <Pagination :links="drawings.links" />
            </template>

        </div>
    </AppLayout>
</template>
