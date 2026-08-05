<script setup>
/**
 * The drawing page: instead of photographing a picture on paper, draw the level
 * right here and play it. The canvas has the engine's exact world size
 * (1500×800), so what is drawn is pixel-for-pixel what the game will parse, and
 * the colour-picking step disappears entirely: the server owns the palette and
 * passes it in as a prop, so every stroke is already in a colour the engine
 * will be told to look for.
 *
 * Drawing uses pointer events so mouse, touch and stylus all behave the same,
 * and undo is a small stack of full-canvas snapshots — crude, but bounded and
 * obviously correct. Before posting, the page checks that the three required
 * colours actually appear on the canvas, because the equivalent failure in the
 * photo flow was a game that silently could not start.
 */
import { onMounted, ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";

import AppLayout from "../Layouts/AppLayout.vue";
import { detectShapes, hexToRgb } from "../game/colorDetection.js";

const props = defineProps({
    palette: {
        type: Object,
        required: true
    }
});

// The engine's fixed world size; the canvas keeps this internal grid no matter
// how large or small CSS displays it.
const WIDTH = 1500;
const HEIGHT = 800;

// The paper colour. Also what the eraser paints with, since erasing here means
// "back to blank paper", not "back to transparent".
const PAPER = "#ffffff";

// Full-canvas snapshots are not small, so the undo stack is capped.
const MAX_UNDO = 20;

// One button per role the server sent. The colours are never hardcoded here:
// the engine will be told exactly these values, so the canvas has to contain
// them byte-for-byte, and the prop is the single source of truth.
const ROLES = Object.entries(props.palette).map(([key, color]) => ({
    key,
    color,
    label: key.charAt(0).toUpperCase() + key.slice(1)
}));

const canvas = ref(null);
const activeRole = ref("platform");
const eraser = ref(false);
// Defaults to a size whose single dot already survives the detector's minimum
// shape size; thinner brushes still work for lines, which cover more pixels.
const brushSize = ref(24);
const undoStack = ref([]);
const validationError = ref("");

const form = useForm({
    levelImage: null
});

// The 2d context and the in-progress stroke are plain variables: nothing in
// the template depends on them, so reactivity would only add overhead.
let ctx = null;
let drawing = false;
let activePointerId = null;
let lastX = 0;
let lastY = 0;

// Undo restores through an <img> load, which is asynchronous; while one is in
// flight, another undo or a fresh stroke would race the onload and land out of
// order, so both wait for it.
let restoring = false;

// toBlob is asynchronous too, so form.processing is still false for a moment
// after the click; this closes the double-click window.
let submitting = false;

onMounted(() => {
    ctx = canvas.value.getContext("2d");

    // Start on white paper, not transparency: a transparent pixel reads as
    // (0, 0, 0) to the colour detector, which is indistinguishable from a
    // black platform colour — an empty canvas would parse as one giant
    // platform filling the whole world.
    paintPaper();
});

function paintPaper() {
    ctx.fillStyle = PAPER;
    ctx.fillRect(0, 0, WIDTH, HEIGHT);
}

function selectRole(key) {
    activeRole.value = key;
    eraser.value = false;
}

// The pointer lands in displayed coordinates; scale it to the fixed internal
// grid before drawing, the same idea as the eyedropper on the settings page.
function canvasPoint(event) {
    const rect = canvas.value.getBoundingClientRect();

    return {
        x: (event.clientX - rect.left) * (WIDTH / rect.width),
        y: (event.clientY - rect.top) * (HEIGHT / rect.height)
    };
}

function startStroke(event) {
    // Only the primary button draws: a right-click would otherwise leave a
    // stray dot just before the context menu opens.
    if (drawing || restoring || event.button !== 0) {
        return;
    }

    drawing = true;
    activePointerId = event.pointerId;

    // Capture the pointer so the stroke keeps going when it briefly leaves
    // the canvas instead of cutting off at the border.
    canvas.value.setPointerCapture(event.pointerId);

    pushSnapshot();
    validationError.value = "";

    const color = eraser.value ? PAPER : props.palette[activeRole.value];

    ctx.lineWidth = brushSize.value;
    ctx.lineCap = "round";
    ctx.lineJoin = "round";
    ctx.strokeStyle = color;
    ctx.fillStyle = color;

    const { x, y } = canvasPoint(event);

    // A click without movement must still leave a mark, and a zero-length
    // stroke is not reliably rendered, so every stroke starts as a filled dot.
    ctx.beginPath();
    ctx.arc(x, y, brushSize.value / 2, 0, Math.PI * 2);
    ctx.fill();

    lastX = x;
    lastY = y;
}

function moveStroke(event) {
    if (! drawing || event.pointerId !== activePointerId) {
        return;
    }

    const { x, y } = canvasPoint(event);

    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(x, y);
    ctx.stroke();

    lastX = x;
    lastY = y;
}

function endStroke(event) {
    if (! drawing || event.pointerId !== activePointerId) {
        return;
    }

    drawing = false;
    activePointerId = null;
}

function pushSnapshot() {
    if (undoStack.value.length >= MAX_UNDO) {
        undoStack.value.shift();
    }

    undoStack.value.push(canvas.value.toDataURL());
}

function undo() {
    // Not while a stroke is in progress: pointer capture only binds the
    // drawing finger, so on touch a second finger can reach this button
    // mid-stroke and would consume the stroke's own snapshot.
    if (restoring || drawing) {
        return;
    }

    const snapshot = undoStack.value.pop();

    if (! snapshot) {
        return;
    }

    restoring = true;

    const image = new Image();

    // Every snapshot is a full opaque canvas, so drawing it back covers
    // everything — no clear needed first.
    image.onload = () => {
        ctx.drawImage(image, 0, 0);
        restoring = false;
    };
    image.src = snapshot;
}

function clearCanvas() {
    if (drawing) {
        return;
    }

    // A native confirm for now, same as deleting a drawing on the account
    // page: it is the one thing between a stray click and a lost drawing.
    if (! window.confirm("Clear the whole drawing? This cannot be undone.")) {
        return;
    }

    paintPaper();
    undoStack.value = [];
    validationError.value = "";
}

/**
 * Whether the ENGINE would actually find each required role — checked by
 * running the game's own shape detector over the canvas, not by looking for
 * coloured pixels. The distinction matters: the detector drops shapes below
 * its minimum size, so a lone small dot of player-blue passes a naive pixel
 * check and still produces a game with no player in it. Running the real code
 * answers the only question that counts: will this parse?
 */
function detectRoleIssues() {
    // Hazards are optional — a level without danger is still playable.
    const required = ROLES.filter((role) => role.key !== "hazard");

    const { data } = ctx.getImageData(0, 0, WIDTH, HEIGHT);

    const detected = detectShapes(
        data,
        WIDTH,
        HEIGHT,
        required.map((role) => ({ key: role.key, color: hexToRgb(role.color) }))
    );

    const missing = [];
    const tooSmall = [];

    for (const role of required) {
        if (detected[role.key].length > 0) {
            continue;
        }

        // Distinguish "never drawn" from "drawn, but too small to survive
        // detection" — the second needs a different hint than the first.
        (hasAnyPixelOf(data, role.color) ? tooSmall : missing).push(role);
    }

    return { missing, tooSmall };
}

function hasAnyPixelOf(data, hex) {
    const value = parseInt(hex.slice(1), 16);

    for (let i = 0; i < data.length; i += 4) {
        if (((data[i] << 16) | (data[i + 1] << 8) | data[i + 2]) === value) {
            return true;
        }
    }

    return false;
}

function listOfRoles(roles) {
    const names = roles.map((role) => `a ${role.label.toLowerCase()}`);

    return names.length > 1
        ? `${names.slice(0, -1).join(", ")} and ${names[names.length - 1]}`
        : names[0];
}

function submit() {
    if (submitting || form.processing) {
        return;
    }

    const { missing, tooSmall } = detectRoleIssues();

    if (missing.length > 0) {
        validationError.value = `Your level still needs ${listOfRoles(missing)}.`;

        return;
    }

    if (tooSmall.length > 0) {
        validationError.value =
            `You drew ${listOfRoles(tooSmall)}, but too small for the game to see — make ${tooSmall.length > 1 ? "them" : "it"} bigger.`;

        return;
    }

    submitting = true;

    canvas.value.toBlob((blob) => {
        form.levelImage = new File([blob], "drawing.png", { type: "image/png" });

        // The server stores the file, remembers the palette and redirects
        // straight into the game; Inertia follows the redirect, so there is
        // nothing to handle on success here. onFinish only matters when the
        // server says no — it reopens the button for another try.
        form.post("/draw-level", {
            onFinish: () => {
                submitting = false;
            }
        });
    }, "image/png");
}
</script>

<template>
    <Head title="Draw a level" />

    <AppLayout>
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-6 py-12">

            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Draw a level</h1>
                <p class="mt-2">
                    Pick a role, then draw with it: platforms are what you stand on, the goal is
                    where you must get, the player is you (one blob is enough), and hazards kill.
                </p>
                <p class="text-sm">
                    Draw bold — tiny dots and very thin marks are too small for the shape
                    detector and get ignored.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-4">

                <button
                    v-for="role in ROLES"
                    :key="role.key"
                    type="button"
                    class="flex items-center gap-2 px-3 py-1.5 text-sm"
                    :class="! eraser && activeRole === role.key ? 'bg-ink text-page' : 'border border-sub'"
                    @click="selectRole(role.key)"
                >
                    <!--
                        The one inline style on the page: the swatch shows the
                        server-chosen colour for this role, which by definition
                        is not part of the house palette.
                    -->
                    <span class="size-3 border border-sub" :style="{ backgroundColor: role.color }"></span>
                    {{ role.label }}
                </button>

                <button
                    type="button"
                    class="px-3 py-1.5 text-sm"
                    :class="eraser ? 'bg-ink text-page' : 'border border-sub'"
                    @click="eraser = ! eraser"
                >
                    Eraser
                </button>

                <label class="flex items-center gap-2 text-sm">
                    Brush
                    <input
                        v-model.number="brushSize"
                        type="range"
                        min="4"
                        max="64"
                        class="accent-ink"
                    >
                </label>

                <button
                    type="button"
                    class="border border-sub px-3 py-1.5 text-sm disabled:opacity-50"
                    :disabled="undoStack.length === 0"
                    @click="undo"
                >
                    Undo
                </button>

                <button
                    type="button"
                    class="border border-sub px-3 py-1.5 text-sm"
                    @click="clearCanvas"
                >
                    Clear
                </button>

            </div>

            <!--
                Internal size is the engine's world, display size is whatever
                fits: w-full with h-auto keeps the 1500×800 ratio. touch-none
                stops touch drawing from scrolling the page instead.
            -->
            <canvas
                ref="canvas"
                :width="WIDTH"
                :height="HEIGHT"
                class="h-auto w-full cursor-crosshair touch-none border border-sub"
                @pointerdown="startStroke"
                @pointermove="moveStroke"
                @pointerup="endStroke"
                @pointerleave="endStroke"
                @pointercancel="endStroke"
            ></canvas>

            <div class="flex flex-col items-center gap-3">

                <button
                    type="button"
                    class="bg-ink px-6 py-2 text-page disabled:opacity-50"
                    :disabled="form.processing"
                    @click="submit"
                >
                    Play your drawing
                </button>

                <p v-if="validationError || form.errors.levelImage" class="text-sm text-error" role="alert">
                    {{ validationError || form.errors.levelImage }}
                </p>

            </div>

        </div>
    </AppLayout>
</template>
