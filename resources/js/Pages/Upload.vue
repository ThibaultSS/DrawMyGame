<script setup>
import { ref } from "vue";
import { Head, router } from "@inertiajs/vue3";

import AppLayout from "../Layouts/AppLayout.vue";
import { isImageFile } from "../imageFile.js";
import { putLevel } from "../levelStore.js";

const MAX_BYTES = 10 * 1024 * 1024;

const error = ref("");
const busy = ref(false);

async function choose(event) {
    const [file] = event.target.files;

    event.target.value = "";

    if (! file) {
        return;
    }

    error.value = "";
    busy.value = true;

    const problem = await unusable(file);

    if (problem) {
        error.value = problem;
        busy.value = false;

        return;
    }

    await putLevel(file);

    router.visit("/game-setting");
}

async function unusable(file) {
    if (! isImageFile(file)) {
        return "Only image files can be used as levels. Try a PNG or a JPG.";
    }

    if (file.type === "image/svg+xml") {
        return "SVG files cannot be used as levels. Try a PNG or a JPG.";
    }

    if (file.size > MAX_BYTES) {
        return "That image is larger than 10 MB.";
    }

    try {
        const bitmap = await createImageBitmap(file);

        bitmap.close();
    } catch {
        return "That file could not be opened as an image.";
    }

    return "";
}
</script>

<template>
    <Head title="Upload your level" />

    <AppLayout>
        <div class="mx-auto flex w-full max-w-3xl flex-col px-6 py-16">

            <h1 class="text-2xl font-semibold tracking-tight">Upload your level</h1>

            <label
                class="mt-8 flex flex-col items-center justify-center gap-3 border-2 border-dashed border-sub px-6 py-24 text-center focus-within:border-ink"
                :class="busy ? 'opacity-50' : 'cursor-pointer hover:border-ink'"
            >
                <input
                    type="file"
                    accept="image/*"
                    class="sr-only"
                    :disabled="busy"
                    @change="choose"
                >

                <span class="text-lg font-medium">
                    Choose an image of your drawing
                </span>

                <span v-if="busy" class="text-sm" role="status">
                    Opening your drawing…
                </span>

                <span v-else class="text-sm">
                    It opens as soon as you pick one. Nothing is uploaded until you save it.
                </span>
            </label>

            <p v-if="error" class="mt-3 text-sm text-error" role="alert">
                {{ error }}
            </p>

        </div>
    </AppLayout>
</template>
