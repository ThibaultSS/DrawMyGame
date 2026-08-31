<script setup>
import { onMounted, ref } from "vue";
import { Head, router } from "@inertiajs/vue3";

import AppLayout from "../Layouts/AppLayout.vue";
import ColourSwatch from "../Components/ColourSwatch.vue";
import { colorClashMessage, colorsTooClose, detectRoleIssues, roleIssueMessage } from "../game/roleCheck.js";
import { DETECTION } from "../game/config.js";
import { putLevel } from "../levelStore.js";

const props = defineProps({
    palette: {
        type: Object,
        required: true
    }
});

const WIDTH = 1500;
const HEIGHT = 800;

const PAPER = "#ffffff";

const MAX_UNDO = 20;

const roles = ref(
    Object.entries(props.palette).map(([key, color]) => ({
        key,
        color,
        label: key.charAt(0).toUpperCase() + key.slice(1)
    }))
);

function colorOf(key) {
    return roles.value.find((role) => role.key === key).color;
}

const canvas = ref(null);
const activeRole = ref("platform");
const eraser = ref(false);
const brushSize = ref(24);
const undoStack = ref([]);
const validationError = ref("");

const submitting = ref(false);

let ctx = null;
let drawing = false;
let activePointerId = null;
let lastX = 0;
let lastY = 0;

let restoring = false;

onMounted(() => {
    ctx = canvas.value.getContext("2d");

    paintPaper();
});

function paintPaper() {
    ctx.fillStyle = PAPER;
    ctx.fillRect(0, 0, WIDTH, HEIGHT);
}

function changeColor(key, next) {
    const previous = colorOf(key);

    if (next === previous) {
        return;
    }

    pushSnapshot();
    repaint(previous, next);

    roles.value = roles.value.map((role) => (role.key === key ? { ...role, color: next } : role));

    validationError.value = "";
}

function repaint(from, to) {
    const image = ctx.getImageData(0, 0, WIDTH, HEIGHT);
    const data = image.data;

    const source = rgb(from);
    const target = rgb(to);
    const paper = rgb(PAPER);
    const limit = DETECTION.colorTolerance * DETECTION.colorTolerance;

    for (let i = 0; i < data.length; i += 4) {
        if (distance(data, i, source) >= limit || distance(data, i, paper) < limit) {
            continue;
        }

        data[i] = target.r;
        data[i + 1] = target.g;
        data[i + 2] = target.b;
    }

    ctx.putImageData(image, 0, 0);
}

function rgb(hex) {
    const value = parseInt(hex.slice(1), 16);

    return { r: (value >> 16) & 255, g: (value >> 8) & 255, b: value & 255 };
}

function distance(data, i, color) {
    const dr = data[i] - color.r;
    const dg = data[i + 1] - color.g;
    const db = data[i + 2] - color.b;

    return dr * dr + dg * dg + db * db;
}

function selectRole(key) {
    activeRole.value = key;
    eraser.value = false;
}

function canvasPoint(event) {
    const rect = canvas.value.getBoundingClientRect();

    return {
        x: (event.clientX - rect.left) * (WIDTH / rect.width),
        y: (event.clientY - rect.top) * (HEIGHT / rect.height)
    };
}

function startStroke(event) {
    if (drawing || restoring || event.button !== 0) {
        return;
    }

    drawing = true;
    activePointerId = event.pointerId;

    canvas.value.setPointerCapture(event.pointerId);

    pushSnapshot();
    validationError.value = "";

    const color = eraser.value ? PAPER : colorOf(activeRole.value);

    ctx.lineWidth = brushSize.value;
    ctx.lineCap = "round";
    ctx.lineJoin = "round";
    ctx.strokeStyle = color;
    ctx.fillStyle = color;

    const { x, y } = canvasPoint(event);

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
    if (restoring || drawing) {
        return;
    }

    const snapshot = undoStack.value.pop();

    if (! snapshot) {
        return;
    }

    restoring = true;

    const image = new Image();

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

    if (! window.confirm("Clear the whole drawing? This cannot be undone.")) {
        return;
    }

    paintPaper();
    undoStack.value = [];
    validationError.value = "";
}

function requiredRoles() {
    return roles.value.filter((role) => role.key !== "hazard");
}

function submit() {
    if (submitting.value) {
        return;
    }

    const clash = colorClashMessage(colorsTooClose(roles.value));

    if (clash) {
        validationError.value = clash;

        return;
    }

    const { data } = ctx.getImageData(0, 0, WIDTH, HEIGHT);
    const problem = roleIssueMessage(detectRoleIssues(data, WIDTH, HEIGHT, requiredRoles()));

    if (problem) {
        validationError.value = problem;

        return;
    }

    submitting.value = true;

    canvas.value.toBlob(async (blob) => {
        await putLevel(blob);

        router.post("/start-game", {
            platformColor: colorOf("platform"),
            goalColor: colorOf("goal"),
            playerColor: colorOf("player"),
            hazardColor: colorOf("hazard")
        }, {
            onFinish: () => {
                submitting.value = false;
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

                <div v-for="role in roles" :key="role.key" class="flex items-center">

                    <button
                        type="button"
                        class="flex items-center gap-2 px-3 py-1.5 text-sm"
                        :class="! eraser && activeRole === role.key ? 'bg-ink text-page' : 'border border-sub'"
                        @click="selectRole(role.key)"
                    >
                        <ColourSwatch :colour="role.color" />
                        {{ role.label }}
                    </button>

                    <input
                        type="color"
                        :value="role.color"
                        class="size-8 cursor-pointer border border-l-0 border-sub bg-page p-1"
                        :aria-label="`Change the ${role.label.toLowerCase()} colour`"
                        @change="changeColor(role.key, $event.target.value)"
                    >

                </div>

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
                    :disabled="submitting"
                    @click="submit"
                >
                    Play your drawing
                </button>

                <p v-if="validationError" class="text-sm text-error" role="alert">
                    {{ validationError }}
                </p>

            </div>

        </div>
    </AppLayout>
</template>
