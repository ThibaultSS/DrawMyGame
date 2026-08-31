<script setup>
import { ref, onMounted } from "vue";
import { Link } from "@inertiajs/vue3";

const STORAGE_KEY = "drawmygame.cookie-notice";

const visible = ref(false);

function dismissed() {
    try {
        return window.localStorage.getItem(STORAGE_KEY) === "1";
    } catch {
        return false;
    }
}

onMounted(() => {
    visible.value = !dismissed();
});

function dismiss() {
    visible.value = false;

    try {
        window.localStorage.setItem(STORAGE_KEY, "1");
    } catch {
    }
}
</script>

<template>
    <aside
        v-if="visible"
        class="fixed bottom-6 left-6 z-40 flex max-w-sm flex-col gap-3 bg-ink p-4 text-sm text-page"
        aria-label="Cookie notice"
    >
        <p>
            DrawMyGame keeps you signed in and keeps its forms safe, and that is
            all its cookies do. Nothing here tracks you.
        </p>

        <div class="flex flex-wrap items-center gap-4">
            <button type="button" class="bg-page px-3 py-1.5 text-ink" @click="dismiss">
                Got it
            </button>

            <Link href="/cookies" class="underline">
                What we store
            </Link>
        </div>
    </aside>
</template>
