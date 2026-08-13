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
import { getLevel, clearLevel } from "../levelStore.js";

const props = defineProps({
    // A saved drawing is served from the server, because that is where it
    // lives. A level that has only been uploaded or drawn is held by the
    // browser and never posted, so this is null and the picture comes out of
    // the level store instead.
    levelImage: { type: String, default: null },
    // Set when this level is already a saved drawing, so Save updates that one
    // instead of storing a second copy.
    drawingId: { type: Number, default: null },
    // How the level stands, and whether this visitor may have a say: voting
    // needs an account, and authors do not rank their own work.
    likes: { type: Number, default: 0 },
    dislikes: { type: Number, default: 0 },
    myVote: { type: Number, default: null },
    canVote: { type: Boolean, default: false },
    platformColor: { type: String, required: true },
    goalColor: { type: String, required: true },
    playerColor: { type: String, required: true },
    // Optional: a level with nothing dangerous in it never picks one.
    hazardColor: { type: String, default: null },
    // Where the sliders start: a replayed drawing plays as its author tuned
    // it, a fresh one starts at the defaults.
    speed: { type: Number, default: 5 },
    jumpHeight: { type: Number, default: 10 }
});

const user = computed(() => usePage().props.auth.user);

// Bound to the sliders. The engine reads the elements' values itself (initial
// value in create(), changes via input events); these refs exist so the
// current positions can be saved with the drawing.
const speedValue = ref(props.speed);
const jumpValue = ref(props.jumpHeight);

let game = null;

// The level itself, kept for the one request that may need to send it: Save.
let levelBlob = null;
let objectUrl = null;

onMounted(async () => {
    const source = await levelSource();

    // Nothing to play: a bookmarked /game, or a store cleared underneath us.
    if (! source) {
        router.visit("/upload");

        return;
    }

    window.levelImage = source;
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

    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }
});

/** The URL the engine loads the level from, or null when there is no level. */
async function levelSource() {
    if (props.levelImage) {
        // Playing a saved drawing. Whatever the browser was still holding is
        // not this level, and keeping it would leave someone's drawing in
        // storage long after they moved on.
        clearLevel();

        return props.levelImage;
    }

    levelBlob = await getLevel();

    if (! levelBlob) {
        return null;
    }

    objectUrl = URL.createObjectURL(levelBlob);

    return objectUrl;
}

/**
 * canvas-confetti stays a CDN script, as it was on the Blade page: it is only
 * ever needed here, and the engine only checks for a global `confetti`
 * function, so the win popup still works if this never loads.
 *
 * The integrity hash is what makes loading a third-party script acceptable:
 * the browser refuses to run the file unless its bytes hash to exactly this,
 * so a compromised CDN cannot substitute code that would then run with the
 * page's own privileges. It pins this exact version — bump both together.
 */
const CONFETTI_URL = "https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js";
const CONFETTI_INTEGRITY = "sha384-sPwMflxqfAN+Q5mvlkLmHiX3PORGbZSXHiSGPTXT9VHCD/AB+b+r+vJWsqprv+7k";

function loadConfetti() {
    if (typeof window.confetti === "function" || document.getElementById("confetti-script")) {
        return;
    }

    const script = document.createElement("script");

    script.id = "confetti-script";
    script.src = CONFETTI_URL;
    script.integrity = CONFETTI_INTEGRITY;
    // Required for integrity to be checked on a cross-origin script.
    script.crossOrigin = "anonymous";

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
    // A full reload is the simplest reliable reset: the colours are in the
    // session and the level is in IndexedDB, so the game rebuilds exactly as it
    // was. (In the rare browser where the level store has fallen back to
    // memory, this is where a level would be lost — see levelStore.js.)
    window.location.reload();
}

/* ------------------------------------------------------------------ *
 * Saving
 * ------------------------------------------------------------------ */

const saving = ref(false);

/**
 * The one request that carries the level image — and only when the server does
 * not have it already.
 *
 * The controller answers with back() and a flash message, which the layout's
 * FlashToast turns into the "Drawing saved." toast. The slider positions go
 * along: they are saved with the drawing, so a replay plays the same.
 */
function saveDrawing() {
    const payload = {
        speed: speedValue.value,
        jumpHeight: jumpValue.value
    };

    if (props.drawingId) {
        // Already on the server: this is either your own drawing being
        // re-tuned or someone else's being copied, and neither needs the
        // browser to send an image it already has.
        payload.drawingId = props.drawingId;
    } else if (levelBlob) {
        // A drawn level is a bare Blob and needs a name to travel as a file;
        // an uploaded one is already a File and keeps the name it came with.
        payload.levelImage = levelBlob instanceof File
            ? levelBlob
            : new File([levelBlob], "drawing.png", { type: "image/png" });
    }

    router.post("/save-drawing", payload, {
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

/* ------------------------------------------------------------------ *
 * Voting
 * ------------------------------------------------------------------ */

const voting = ref(false);

/**
 * Pressing the button you already chose takes the vote back — the server
 * treats a repeat as "never mind", so there is no third button for it.
 *
 * preserveState for the same reason Save needs it: a remount would boot a
 * second game over the one that is running. The refreshed props are what move
 * the counts.
 */
function vote(value) {
    router.post(`/drawing/${props.drawingId}/vote`, { value }, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => (voting.value = true),
        onFinish: () => (voting.value = false)
    });
}
</script>

<template>
    <Head title="Game" />

    <AppLayout>
        <div class="mx-auto flex w-full max-w-[1560px] flex-col gap-6 px-6 py-10 lg:flex-row lg:items-start">

            <div class="relative w-full lg:flex-1">
                <!-- Phaser mounts its canvas in here (config.parent). -->
                <div id="game-container" class="w-full"></div>

                <!-- Shown until the scene's create() hides it. -->
                <div id="loading-screen" class="absolute inset-0 flex items-center justify-center">
                    <p class="animate-pulse text-lg">Loading…</p>
                </div>
            </div>

            <!--
                A column beside the game on wide screens, so the sliders and
                Save are reachable without scrolling past 800px of canvas. On
                narrow screens it drops underneath, where a side column would
                leave the game too small to play.
            -->
            <div class="flex w-full flex-col gap-4 text-sm lg:w-56 lg:shrink-0">

                <button
                    v-if="user"
                    type="button"
                    class="w-full bg-ink px-4 py-2 text-page disabled:opacity-50"
                    :disabled="saving"
                    @click="saveDrawing"
                >
                    {{ saving ? "Saving…" : "Save Drawing" }}
                </button>

                <!-- The labels sit above the sliders: a narrow column has
                     height to spare and not much width. The ids are the
                     engine's contract — see the header comment. -->
                <label class="flex flex-col gap-1">
                    Speed
                    <input
                        id="speedSlider"
                        v-model.number="speedValue"
                        type="range"
                        min="1"
                        max="20"
                        class="w-full accent-ink"
                    >
                </label>

                <label class="flex flex-col gap-1">
                    Jump Height
                    <input
                        id="jumpSlider"
                        v-model.number="jumpValue"
                        type="range"
                        min="5"
                        max="30"
                        class="w-full accent-ink"
                    >
                </label>

                <!--
                    Only a saved, published level has anything to vote on. The
                    counts are shown to everyone; the buttons appear only for
                    someone who may actually cast one.
                -->
                <template v-if="drawingId">
                    <div v-if="canVote" class="flex gap-2">
                        <button
                            type="button"
                            class="flex-1 px-3 py-2 disabled:opacity-50"
                            :class="myVote === 1 ? 'bg-ink text-page' : 'border border-sub'"
                            :aria-pressed="myVote === 1"
                            :disabled="voting"
                            @click="vote(1)"
                        >
                            Like {{ likes }}
                        </button>

                        <button
                            type="button"
                            class="flex-1 px-3 py-2 disabled:opacity-50"
                            :class="myVote === -1 ? 'bg-ink text-page' : 'border border-sub'"
                            :aria-pressed="myVote === -1"
                            :disabled="voting"
                            @click="vote(-1)"
                        >
                            Dislike {{ dislikes }}
                        </button>
                    </div>

                    <p v-else>{{ likes }} likes · {{ dislikes }} dislikes</p>
                </template>

                <button type="button" class="w-full border border-sub px-4 py-2" @click="goBack">
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
