<script setup>
/**
 * The toast for one-shot messages. It lives in AppLayout, so any page whose
 * action ends in back()->with('message', ...) gets feedback without writing its
 * own timer — saving, publishing and deleting all land here.
 *
 * It listens two ways. The watcher covers anything the server flashed; the
 * document event covers what the server never saw, which is the interesting
 * case: a request that was refused, or a level that was gone before a request
 * was made. A DOM event rather than writing into the shared props, for the same
 * reason the engine announces a win that way — nothing has to reach into
 * anything else's state.
 */
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

// The watcher observes the flash object rather than the message string: the
// object is rebuilt on every Inertia response, so doing the same action twice
// in a row still re-triggers the toast even though the text is identical.
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
