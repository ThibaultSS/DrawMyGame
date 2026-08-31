<script setup>
import { onMounted, onUnmounted, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";

const page = usePage();

const toast = ref(null);
let hideTimer = null;

function show(message) {
    if (! message) {
        return;
    }

    toast.value = message;

    clearTimeout(hideTimer);
    hideTimer = setTimeout(() => {
        toast.value = null;
    }, 3000);
}

watch(() => page.props.flash, (flash) => show(flash?.message));

function onFlashEvent(event) {
    show(event.detail?.message);
}

onMounted(() => document.addEventListener("flash", onFlashEvent));

onUnmounted(() => {
    document.removeEventListener("flash", onFlashEvent);
    clearTimeout(hideTimer);
});
</script>

<template>
    <p
        v-if="toast"
        class="fixed right-6 bottom-6 bg-ink px-4 py-3 text-sm text-page"
        role="status"
    >
        {{ toast }}
    </p>
</template>
