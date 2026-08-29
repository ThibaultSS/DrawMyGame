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
import { Head, Link, router, usePage } from "@inertiajs/vue3";

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
    // Somebody else's level can be kept to play again. Saving it used to make
    // a copy you owned; now it stays theirs and only the feel is yours.
    isFavourite: { type: Boolean, default: false },
    canFavourite: { type: Boolean, default: false },
    // How this level has been played. Empty for a level the browser is holding:
    // there is nothing to record against until it has been saved.
    beaten: { type: Number, default: 0 },
    attempted: { type: Number, default: 0 },
    myBestMs: { type: Number, default: null },
    fastest: { type: Array, default: () => [] },
    canRecord: { type: Boolean, default: false },
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
    // Saying so, because leaving in silence looks like the page gave up.
    if (! source) {
        router.visit("/upload", {
            onFinish: () => flash("That level is no longer in this browser. Pick a drawing to start again.")
        });

        return;
    }

    window.levelImage = source;
    window.platformColor = props.platformColor;
    window.goalColor = props.goalColor;
    window.playerColor = props.playerColor;
    window.hazardColor = props.hazardColor;
    window.gamePaused = false;

    loadConfetti();

    // Listen before booting: create() starts the clock, and a very short level
    // could in principle be won before a listener added afterwards existed.
    if (recordsPlays.value) {
        document.addEventListener("level-won", recordWin);
        recordAttempt();
    }

    const { bootGame } = await import("../game/main.js");
    game = bootGame();
});

onUnmounted(() => {
    document.removeEventListener("level-won", recordWin);

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

/**
 * Whether this device is driven by a finger. Read at setup rather than on
 * mount, because the engine looks the buttons up by id as it boots — decided a
 * tick later they would not be in the DOM yet and nothing would be bound.
 */
const isTouch =
    typeof window !== "undefined" &&
    window.matchMedia("(pointer: coarse)").matches;

const saving = ref(false);

/**
 * Says something through the toast the layout already owns.
 *
 * The server's own flash covers anything that succeeded. This covers what it
 * never saw: a request that was refused, or a level that was gone before a
 * request was made.
 */
function flash(message) {
    document.dispatchEvent(new CustomEvent("flash", { detail: { message } }));
}

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
        onFinish: () => (saving.value = false),
        // A save can be refused — the level unpublished in another tab, the
        // upload limit reached, the session gone. Without this the button
        // simply came back and nothing was saved, with nothing said.
        onError: () => flash("That did not save. The level may have changed, or you may have saved too many just now."),
    });
}

/**
 * Keep, or stop keeping, somebody else's level.
 *
 * The sliders' current values go with it, so the level opens at your feel next
 * time rather than its author's — which is the part copying it used to buy.
 */
function toggleFavourite() {
    const options = {
        preserveState: true,
        preserveScroll: true,
        onError: () => flash("That did not work. The level may no longer be published.")
    };

    if (props.isFavourite) {
        router.delete(`/drawing/${props.drawingId}/favourite`, options);

        return;
    }

    router.post(
        `/drawing/${props.drawingId}/favourite`,
        { speed: speedValue.value, jumpHeight: jumpValue.value },
        options
    );
}

/**
 * Whether this play is worth recording: a level the server knows about, and a
 * visitor a time can belong to.
 */
const recordsPlays = computed(() => Boolean(props.drawingId) && props.canRecord);

/** 92_512 ms as "1:32.5" — the shape a time is read in. */
function formatTime(ms) {
    const total = ms / 1000;
    const minutes = Math.floor(total / 60);
    const seconds = (total % 60).toFixed(1).padStart(4, "0");

    return `${minutes}:${seconds}`;
}

/**
 * Records that this level was played, and how it went.
 *
 * Only for a saved level and only when signed in, because a time has to belong
 * to somebody. The engine says when the level was won by dispatching level-won
 * on the document; it does not know this page exists.
 */
function recordAttempt() {
    router.post(`/drawing/${props.drawingId}/attempt`, {}, {
        preserveState: true,
        preserveScroll: true,
        only: ["attempted"]
    });
}

function recordWin(event) {
    router.post(
        `/drawing/${props.drawingId}/completed`,
        { timeMs: event.detail.ms },
        // The standings are what changed, so only those come back — the level
        // itself must not be re-sent under a running game.
        { preserveState: true, preserveScroll: true, only: ["beaten", "attempted", "myBestMs", "fastest"] }
    );
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
        <!--
            lg:items-center rather than items-start: the controls column is far
            shorter than the canvas, so aligned to the top it sat up by the
            first row of the level with a long empty gap under it.
        -->
        <div class="mx-auto flex w-full max-w-[1560px] flex-col gap-6 px-6 py-10 lg:flex-row lg:items-center">

            <div class="flex w-full flex-col gap-4 lg:flex-1">

                <!-- The frame hugs the canvas: no padding, like the bordered
                     images elsewhere on the site. -->
                <div class="relative w-full border border-sub">
                    <!-- Phaser mounts its canvas in here (config.parent). -->
                    <div id="game-container" class="w-full"></div>

                    <!-- Shown until the scene's create() hides it. -->
                    <div id="loading-screen" class="absolute inset-0 flex items-center justify-center">
                        <p class="animate-pulse text-lg">Loading…</p>
                    </div>
                </div>

                <!--
                    The engine binds these by id, so the ids are part of its
                    contract like speedSlider and jumpSlider.

                    touch-none stops a press from scrolling the page, and
                    select-none stops a quick double tap selecting the arrow
                    glyph instead of jumping.
                -->
                <div v-if="isTouch" class="flex items-center justify-between gap-4 select-none">
                    <div class="flex gap-4">
                        <button
                            id="touch-left"
                            type="button"
                            class="flex size-16 touch-none items-center justify-center border border-sub text-2xl"
                            aria-label="Move left"
                        >&larr;</button>

                        <button
                            id="touch-right"
                            type="button"
                            class="flex size-16 touch-none items-center justify-center border border-sub text-2xl"
                            aria-label="Move right"
                        >&rarr;</button>
                    </div>

                    <button
                        id="touch-jump"
                        type="button"
                        class="flex size-16 touch-none items-center justify-center border border-sub text-2xl"
                        aria-label="Jump"
                    >&uarr;</button>
                </div>

                <!-- Only a saved level has anything to record against: a level
                     the browser is holding is not a thing anyone else can
                     have played. -->
                <section v-if="drawingId" class="border border-sub p-4 text-sm">

                    <h2 class="font-medium">Times</h2>

                    <p class="mt-2">
                        <template v-if="attempted > 0">
                            Beaten by {{ beaten }} of {{ attempted }} who tried.
                        </template>
                        <template v-else>
                            Nobody has finished this level yet. Be the first.
                        </template>
                    </p>

                    <p v-if="myBestMs !== null" class="mt-1">
                        Your best: {{ formatTime(myBestMs) }}
                    </p>

                    <p v-else-if="! canRecord" class="mt-1">
                        Sign in to have your time recorded.
                    </p>

                    <ol v-if="fastest.length > 0" class="mt-4 flex flex-col gap-1">
                        <li
                            v-for="(entry, index) in fastest"
                            :key="index"
                            class="flex justify-between gap-4"
                        >
                            <span class="min-w-0 truncate">{{ index + 1 }}. {{ entry.username }}</span>
                            <span class="shrink-0">{{ formatTime(entry.ms) }}</span>
                        </li>
                    </ol>

                </section>

            </div>

            <!--
                A column beside the game on wide screens, so the sliders and
                Save are reachable without scrolling past 800px of canvas. On
                narrow screens it drops underneath, where a side column would
                leave the game too small to play.
            -->
            <div class="flex w-full flex-col gap-4 border border-sub p-4 text-sm lg:w-64 lg:shrink-0">

                <!-- Your own level, or one the browser is holding: saving keeps
                     the picture and the whole game with it. -->
                <button
                    v-if="user && ! canFavourite"
                    type="button"
                    class="w-full bg-ink px-4 py-2 text-page disabled:opacity-50"
                    :disabled="saving"
                    @click="saveDrawing"
                >
                    {{ saving ? "Saving…" : "Save Drawing" }}
                </button>

                <!-- Somebody else's: keeping it leaves it theirs. Filled in
                     when it is already kept, like the vote buttons. -->
                <button
                    v-else-if="canFavourite"
                    type="button"
                    class="w-full px-4 py-2"
                    :class="isFavourite ? 'bg-ink text-page' : 'border border-sub'"
                    :aria-pressed="isFavourite"
                    @click="toggleFavourite"
                >
                    {{ isFavourite ? "Saved to your account" : "Save to your account" }}
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

                <!-- Somewhere to go next, rather than only back. Both are here
                     because after a level you either want another one or the
                     gallery you came from. -->
                <Link href="/random-level" class="w-full border border-sub px-4 py-2 text-center">
                    Another random level
                </Link>

                <Link href="/community" class="w-full border border-sub px-4 py-2 text-center">
                    Back to community
                </Link>

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
