<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";

import confetti from "canvas-confetti";

import AppLayout from "../Layouts/AppLayout.vue";
import GamePopup from "../Components/GamePopup.vue";
import { getLevel, clearLevel } from "../levelStore.js";

const props = defineProps({
    levelImage: { type: String, default: null },
    drawingId: { type: Number, default: null },
    likes: { type: Number, default: 0 },
    dislikes: { type: Number, default: 0 },
    myVote: { type: Number, default: null },
    canVote: { type: Boolean, default: false },
    isFavourite: { type: Boolean, default: false },
    canFavourite: { type: Boolean, default: false },
    beaten: { type: Number, default: 0 },
    attempted: { type: Number, default: 0 },
    myBestMs: { type: Number, default: null },
    fastest: { type: Array, default: () => [] },
    canRecord: { type: Boolean, default: false },
    platformColor: { type: String, required: true },
    goalColor: { type: String, required: true },
    playerColor: { type: String, required: true },
    hazardColor: { type: String, default: null },
    speed: { type: Number, default: 5 },
    jumpHeight: { type: Number, default: 10 }
});

const user = computed(() => usePage().props.auth.user);

const speedValue = ref(props.speed);
const jumpValue = ref(props.jumpHeight);

let game = null;

let levelBlob = null;
let objectUrl = null;

onMounted(async () => {
    const source = await levelSource();

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

    document.addEventListener("level-ended", onLevelEnded);
    document.addEventListener("level-ready", onLevelReady);

    if (recordsPlays.value) {
        recordAttempt();
    }

    const { bootGame } = await import("../game/main.js");
    game = bootGame();
});

onUnmounted(() => {
    document.removeEventListener("level-ended", onLevelEnded);
    document.removeEventListener("level-ready", onLevelReady);

    game?.destroy(true);
    game = null;

    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }
});

async function levelSource() {
    if (props.levelImage) {
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

function onLevelEnded(event) {
    const { won, ms } = event.detail;

    popupMessage.value = won ? "You won!" : "You lost!";

    if (! won) {
        return;
    }

    celebrate();

    if (recordsPlays.value) {
        recordWin(ms);
    }
}

function onLevelReady() {
    loading.value = false;
}

function celebrate() {
    confetti({ particleCount: 200, spread: 120, origin: { y: 0.4 } });

    setTimeout(() => confetti({ particleCount: 150, angle: 60, spread: 80, origin: { x: 0, y: 0.5 } }), 200);
    setTimeout(() => confetti({ particleCount: 150, angle: 120, spread: 80, origin: { x: 1, y: 0.5 } }), 400);
    setTimeout(() => confetti({ particleCount: 300, spread: 160, origin: { y: 0.3 } }), 600);
}

function closePopup() {
    window.gamePaused = false;
    popupMessage.value = null;
}

function retry() {
    window.location.reload();
}

const isTouch =
    typeof window !== "undefined" &&
    window.matchMedia("(pointer: coarse)").matches;

const popupMessage = ref(null);
const loading = ref(true);
const saving = ref(false);

function flash(message) {
    document.dispatchEvent(new CustomEvent("flash", { detail: { message } }));
}

function saveDrawing() {
    const payload = {
        speed: speedValue.value,
        jumpHeight: jumpValue.value
    };

    if (props.drawingId) {
        payload.drawingId = props.drawingId;
    } else if (levelBlob) {
        payload.levelImage = levelBlob instanceof File
            ? levelBlob
            : new File([levelBlob], "drawing.png", { type: "image/png" });
    }

    router.post("/save-drawing", payload, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => (saving.value = true),
        onFinish: () => (saving.value = false),
        onError: () => flash("That did not save. The level may have changed, or you may have saved too many just now."),
    });
}

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

const recordsPlays = computed(() => Boolean(props.drawingId) && props.canRecord);

function formatTime(ms) {
    const total = ms / 1000;
    const minutes = Math.floor(total / 60);
    const seconds = (total % 60).toFixed(1).padStart(4, "0");

    return `${minutes}:${seconds}`;
}

function recordAttempt() {
    router.post(`/drawing/${props.drawingId}/attempt`, {}, {
        preserveState: true,
        preserveScroll: true,
        only: ["attempted"]
    });
}

function recordWin(ms) {
    router.post(
        `/drawing/${props.drawingId}/completed`,
        { timeMs: ms },
        { preserveState: true, preserveScroll: true, only: ["beaten", "attempted", "myBestMs", "fastest"] }
    );
}

function goBack() {
    window.history.back();
}

const voting = ref(false);

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
        <div class="mx-auto flex w-full max-w-[1560px] flex-col gap-6 px-6 py-10 lg:flex-row lg:items-center">

            <div class="flex w-full flex-col gap-4 lg:flex-1">

                <div class="relative w-full border border-sub">
                    <div id="game-container" class="w-full"></div>

                    <div v-if="loading" class="absolute inset-0 flex items-center justify-center">
                        <p class="animate-pulse text-lg">Loading…</p>
                    </div>
                </div>

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

            <div class="flex w-full flex-col gap-4 border border-sub p-4 text-sm lg:w-64 lg:shrink-0">

                <button
                    v-if="user && ! canFavourite"
                    type="button"
                    class="w-full bg-ink px-4 py-2 text-page disabled:opacity-50"
                    :disabled="saving"
                    @click="saveDrawing"
                >
                    {{ saving ? "Saving…" : "Save Drawing" }}
                </button>

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

        <GamePopup
            v-if="popupMessage"
            :message="popupMessage"
            @close="closePopup"
            @retry="retry"
        />
    </AppLayout>
</template>

<style>
#game-container canvas {
    display: block;
    margin-inline: auto;
    max-width: 100%;
    height: auto;
}
</style>
