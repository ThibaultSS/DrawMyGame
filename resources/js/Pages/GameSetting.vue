<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";

import { detectRoleIssues, listOfRoles } from "../game/roleCheck.js";

import AppLayout from "../Layouts/AppLayout.vue";
import ColourSwatch from "../Components/ColourSwatch.vue";
import { getLevel } from "../levelStore.js";

const props = defineProps({
    image: {
        type: String,
        default: null
    }
});

const imageUrl = ref(props.image);

let objectUrl = null;

onMounted(async () => {
    if (imageUrl.value) {
        return;
    }

    const blob = await getLevel();

    if (! blob) {
        router.visit("/upload");

        return;
    }

    objectUrl = URL.createObjectURL(blob);
    imageUrl.value = objectUrl;
});

onUnmounted(() => {
    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
    }
});

const ROLES = [
    { key: "platform", label: "Pick Platform", name: "platform", required: true },
    { key: "goal", label: "Pick Goal", name: "goal", required: true },
    { key: "player", label: "Pick Player", name: "player", required: true },
    { key: "hazard", label: "Pick Hazard", name: "hazard", required: false }
];

const currentSelection = ref(null);

const preview = ref(null);

const validationError = ref("");
const checking = ref(false);

const form = useForm({
    platformColor: "",
    goalColor: "",
    playerColor: "",
    hazardColor: ""
});

const allPicked = computed(
    () => ROLES.filter((role) => role.required).every((role) => form[`${role.key}Color`] !== "")
);

const firstError = computed(
    () => ROLES.map((role) => form.errors[`${role.key}Color`]).find(Boolean)
);

function pickColor(event) {
    if (! currentSelection.value) {
        return;
    }

    const image = preview.value;
    const ctx = photoContext();

    const rect = image.getBoundingClientRect();
    const x = (event.clientX - rect.left) * (image.naturalWidth / rect.width);
    const y = (event.clientY - rect.top) * (image.naturalHeight / rect.height);

    const pixel = ctx.getImageData(Math.floor(x), Math.floor(y), 1, 1).data;

    const hex =
        "#" +
        pixel[0].toString(16).padStart(2, "0") +
        pixel[1].toString(16).padStart(2, "0") +
        pixel[2].toString(16).padStart(2, "0");

    form[`${currentSelection.value}Color`] = hex;
}

function photoContext() {
    const image = preview.value;
    const canvas = document.createElement("canvas");
    const ctx = canvas.getContext("2d");

    canvas.width = image.naturalWidth;
    canvas.height = image.naturalHeight;
    ctx.drawImage(image, 0, 0);

    return ctx;
}

async function submit() {
    validationError.value = "";
    checking.value = true;

    await nextTick();

    const image = preview.value;
    const { data } = photoContext().getImageData(0, 0, image.naturalWidth, image.naturalHeight);

    const { missing, tooSmall } = detectRoleIssues(
        data,
        image.naturalWidth,
        image.naturalHeight,
        ROLES
            .filter((role) => role.required)
            .map((role) => ({ key: role.key, color: form[`${role.key}Color`], label: role.name }))
    );

    checking.value = false;

    const unfound = [...missing, ...tooSmall];

    if (unfound.length > 0) {
        validationError.value =
            `The game could not find ${listOfRoles(unfound)} in this photo. Pick a bolder colour, or one that covers a larger area — small or faint marks are ignored.`;

        return;
    }

    form
        .transform((data) => ({
            ...data,
            hazardColor: data.hazardColor || null
        }))
        .post("/start-game");
}
</script>

<template>
    <Head title="Select colors" />

    <AppLayout>
        <div class="mx-auto flex w-full max-w-4xl flex-col items-center gap-8 px-6 py-12">

            <div class="text-center">
                <h1 class="text-2xl font-semibold tracking-tight">Select Colors</h1>
                <p class="mt-2">Click a button, then click the corresponding color on the image.</p>
                <p class="mt-1 text-sm">Hazards are optional — leave them out for a level with nothing dangerous in it.</p>
            </div>

            <div class="flex flex-wrap justify-center gap-4">
                <div v-for="role in ROLES" :key="role.key" class="flex items-center gap-3">
                    <button
                        type="button"
                        class="px-3 py-1.5 text-sm"
                        :class="currentSelection === role.key ? 'bg-ink text-page' : 'border border-sub'"
                        @click="currentSelection = role.key"
                    >
                        {{ role.label }}
                    </button>

                    <ColourSwatch size="size-10" :colour="form[`${role.key}Color`]" />

                    <span v-if="! role.required" class="text-sm">optional</span>
                </div>
            </div>

            <img
                v-if="imageUrl"
                ref="preview"
                :src="imageUrl"
                alt="Your uploaded level"
                class="w-full max-w-3xl cursor-crosshair border border-sub"
                @click="pickColor"
            >

            <form class="flex flex-col items-center gap-3" @submit.prevent="submit">
                <button
                    type="submit"
                    class="bg-ink px-6 py-2 text-page disabled:opacity-50"
                    :disabled="! allPicked || checking || form.processing"
                >
                    {{ checking ? "Checking…" : "Start Game" }}
                </button>

                <p v-if="validationError" class="max-w-md text-center text-sm text-error" role="alert">
                    {{ validationError }}
                </p>

                <p v-if="firstError" class="text-sm text-error" role="alert">
                    {{ firstError }}
                </p>
            </form>

        </div>
    </AppLayout>
</template>
