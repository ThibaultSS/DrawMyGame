<script setup>
/**
 * The saved drawings, with publish and delete.
 *
 * Both actions used to redirect to /account, so every click reloaded the whole
 * page, lost the scroll position and said nothing about whether it had worked.
 * Now the controller returns back(), Inertia re-fetches the props, and only the
 * card that changed re-renders; the layout's FlashToast shows the outcome.
 */
import { ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";

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

// Ids currently being changed, so a card's buttons can be disabled while its
// request is in flight and a double click cannot fire the action twice.
const busy = ref(new Set());

function isBusy(id) {
    return busy.value.has(id);
}

function markBusy(id, running) {
    const next = new Set(busy.value);

    running ? next.add(id) : next.delete(id);
    busy.value = next;
}

function togglePublish(drawing) {
    router.post(`/drawing/${drawing.id}/publish`, {}, {
        preserveScroll: true,
        onStart: () => markBusy(drawing.id, true),
        onFinish: () => markBusy(drawing.id, false)
    });
}

function destroy(drawing) {
    // A native confirm for now. It is the one thing standing between a stray
    // click and a deleted drawing, so it is worth having before it is pretty.
    if (! window.confirm("Delete this drawing? This cannot be undone.")) {
        return;
    }

    router.delete(`/drawing/${drawing.id}`, {
        preserveScroll: true,
        onStart: () => markBusy(drawing.id, true),
        onFinish: () => markBusy(drawing.id, false)
    });
}
</script>

<template>
    <Head title="My drawings" />

    <AppLayout>
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-8 px-6 py-12">

            <h1 class="text-2xl font-semibold tracking-tight">My drawings</h1>

            <p v-if="drawings.data.length === 0" class="flex flex-col items-start gap-4">
                <span>You have not saved any drawings yet.</span>
                <Link href="/upload" class="bg-ink px-4 py-2 text-page">Upload a drawing</Link>
            </p>

            <template v-else>
                <ul class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <li
                        v-for="drawing in drawings.data"
                        :key="drawing.id"
                        class="flex flex-col gap-3 border border-sub p-3"
                    >
                        <Link :href="`/play/${drawing.id}`" class="block">
                            <img
                                :src="drawing.image"
                                alt="Saved drawing"
                                class="aspect-4/3 w-full object-cover"
                            >
                        </Link>

                        <div class="flex items-center justify-between gap-3">
                            <button
                                type="button"
                                class="px-3 py-1.5 text-sm disabled:opacity-50"
                                :class="drawing.published ? 'bg-ink text-page' : 'border border-sub'"
                                :disabled="isBusy(drawing.id)"
                                @click="togglePublish(drawing)"
                            >
                                {{ drawing.published ? "Published" : "Publish" }}
                            </button>

                            <button
                                type="button"
                                class="px-3 py-1.5 text-sm text-error disabled:opacity-50"
                                :disabled="isBusy(drawing.id)"
                                @click="destroy(drawing)"
                            >
                                Delete
                            </button>
                        </div>
                    </li>
                </ul>

                <Pagination :links="drawings.links" />
            </template>

        </div>
    </AppLayout>
</template>
