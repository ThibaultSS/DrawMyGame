<script setup>
/**
 * The game page. All gameplay lives in resources/js/game/ — this component only
 * hands the engine its inputs and hosts the DOM it talks to.
 *
 * The contract with the engine has two halves. Inputs: the engine reads
 * window.levelImage and the four colour globals, which are set here from the
 * props before it boots. Elements: the engine looks up game-container,
 * loading-screen, popup, popup-message, speedSlider and jumpSlider by id, so
 * renaming any of those here means renaming them in main.js too.
 *
 * The engine is imported lazily on mount rather than at the top of the file, so
 * Vite splits Phaser into a chunk that only the game page downloads.
 */
import { computed, onMounted, onUnmounted, ref } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";

import AppLayout from "../Layouts/AppLayout.vue";

const props = defineProps({
    levelImage: { type: String, required: true },
    platformColor: { type: String, required: true },
    goalColor: { type: String, required: true },
    playerColor: { type: String, required: true },
    hazardColor: { type: String, required: true }
});

const user = computed(() => usePage().props.auth.user);

let game = null;

onMounted(async () => {
    window.levelImage = props.levelImage;
    window.platformColor = props.platformColor;
    window.goalColor = props.goalColor;
    window.playerColor = props.playerColor;
    window.hazardColor = props.hazardColor;
    window.gamePaused = false;

    loadConfetti();

    const { bootGame } = await import("../game/main.js");
    game = bootGame();
});

onUnmounted(() => {
    // Stops the render loop and removes the canvas. Without this, leaving the
    // page and coming back would stack a second game on top of the first.
    game?.destroy(true);
    game = null;
});

/**
 * canvas-confetti stays a CDN script, as it was on the Blade page: it is only
 * ever needed here, and the engine only checks for a global `confetti` function.
 */
function loadConfetti() {
    if (typeof window.confetti === "function" || document.getElementById("confetti-script")) {
        return;
    }

    const script = document.createElement("script");

    script.id = "confetti-script";
    script.src = "https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js";

    document.head.appendChild(script);
}

/* ------------------------------------------------------------------ *
 * Popup
 * ------------------------------------------------------------------ */

// The engine opens the popup (and pauses itself) by setting the element's
// display directly; closing it again is this page's job.
function closePopup() {
    window.gamePaused = false;

    document.getElementById("popup").style.display = "none";
}

function retry() {
    // A full reload is the simplest reliable reset: the session still holds the
    // level and colours, so the game rebuilds exactly as it was.
    window.location.reload();
}

/* ------------------------------------------------------------------ *
 * Saving
 * ------------------------------------------------------------------ */

const saving = ref(false);

// The controller answers with back() and a flash message, which the layout's
// FlashToast turns into the "Drawing saved." toast.
function saveDrawing() {
    router.post("/save-drawing", {}, {
        // preserveState matters: without it Inertia remounts this component and
        // boots a second game over the one that is running.
        preserveState: true,
        preserveScroll: true,
        onStart: () => (saving.value = true),
        onFinish: () => (saving.value = false)
    });
}

function goBack() {
    window.history.back();
}
</script>

<template>
    <Head title="Game" />

    <AppLayout>
        <div class="mx-auto flex w-full max-w-[1560px] flex-col items-center gap-6 px-6 py-10">

            <div class="relative w-full">
                <!-- Phaser mounts its canvas in here (config.parent). -->
                <div id="game-container" class="w-full"></div>

                <!-- Shown until the scene's create() hides it. -->
                <div id="loading-screen" class="absolute inset-0 flex items-center justify-center">
                    <p class="animate-pulse text-lg">Loading…</p>
                </div>
            </div>

            <div class="flex w-full flex-wrap items-center justify-center gap-6 text-sm">

                <button
                    v-if="user"
                    type="button"
                    class="bg-ink px-4 py-2 text-page disabled:opacity-50"
                    :disabled="saving"
                    @click="saveDrawing"
                >
                    {{ saving ? "Saving…" : "Save Drawing" }}
                </button>

                <label class="flex items-center gap-3">
                    <input id="speedSlider" type="range" min="1" max="20" value="5" class="accent-ink">
                    Speed
                </label>

                <label class="flex items-center gap-3">
                    <input id="jumpSlider" type="range" min="5" max="30" value="10" class="accent-ink">
                    Jump Height
                </label>

                <button type="button" class="border border-sub px-4 py-2" @click="goBack">
                    Go Back
                </button>

            </div>

        </div>

        <!--
            Won/lost overlay. Its display is flipped by the engine's showPopup,
            so it is a plain style attribute, not a v-if: the element has to
            exist for getElementById to find it.
        -->
        <div id="popup" style="display: none;" class="fixed inset-0 z-40 items-center justify-center bg-ink/50">
            <div class="flex flex-col items-center gap-6 border border-sub bg-page px-12 py-10 text-center">

                <h1 id="popup-message" class="text-3xl font-semibold tracking-tight"></h1>

                <div class="flex gap-4">
                    <button type="button" class="border border-sub px-4 py-2" @click="closePopup">
                        Close
                    </button>
                    <button type="button" class="bg-ink px-4 py-2 text-page" @click="retry">
                        Retry
                    </button>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style>
/*
 * The canvas is a fixed 1500x800 world (see game/config.js). Scaling it down
 * with CSS keeps it on screen at smaller widths; Phaser maps pointer input
 * through the canvas bounds, so input keeps working at any scale.
 */
#game-container canvas {
    display: block;
    margin-inline: auto;
    max-width: 100%;
    height: auto;
}
</style>
