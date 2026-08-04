<script setup>
/**
 * The toast for one-shot flash messages. It lives in AppLayout, so any page
 * whose action ends in back()->with('message', ...) gets feedback without
 * writing its own timer — saving, publishing and deleting all land here.
 *
 * The watcher observes the flash object rather than the message string: the
 * object is rebuilt on every Inertia response, so doing the same action twice
 * in a row still re-triggers the toast even though the text is identical.
 */
import { ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";

const page = usePage();

const toast = ref(null);
let hideTimer = null;

watch(() => page.props.flash, (flash) => {
    if (! flash?.message) {
        return;
    }

    toast.value = flash.message;

    clearTimeout(hideTimer);
    hideTimer = setTimeout(() => {
        toast.value = null;
    }, 3000);
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
