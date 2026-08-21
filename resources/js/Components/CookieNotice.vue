<script setup>
/**
 * A short, dismissible note about what this site stores.
 *
 * Deliberately not an Accept/Reject banner. DrawMyGame sets two cookies, both
 * strictly necessary — the session and the CSRF token — and there is no
 * analytics, advertising or third-party script anywhere in the application.
 * Strictly necessary cookies do not need consent, so offering a choice that
 * does not exist would be less honest than saying plainly what is stored.
 */
import { ref, onMounted } from "vue";
import { Link } from "@inertiajs/vue3";

const STORAGE_KEY = "drawmygame.cookie-notice";

const visible = ref(false);

/**
 * localStorage rather than a cookie: setting a cookie to record that somebody
 * read a notice about cookies is exactly the kind of detail that would make the
 * notice untrue.
 *
 * A privacy mode can refuse it outright, the same way one can refuse the
 * IndexedDB the level store wants, so both ends are guarded. The honest failure
 * is that the notice appears again next visit.
 */
function dismissed() {
    try {
        return window.localStorage.getItem(STORAGE_KEY) === "1";
    } catch {
        return false;
    }
}

// Only after mount, so the notice never flashes on a page it was already
// dismissed on.
onMounted(() => {
    visible.value = !dismissed();
});

function dismiss() {
    visible.value = false;

    try {
        window.localStorage.setItem(STORAGE_KEY, "1");
    } catch {
        // Refused. It will be shown again next time, which is the worst that
        // this can do.
    }
}
</script>

<template>
    <!--
        Bottom left, because FlashToast already owns the bottom right corner: a
        full-width bar or a right-hand card would sit under the toast the first
        time somebody saves a drawing.
    -->
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
