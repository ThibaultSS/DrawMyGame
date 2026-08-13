<script setup>
/**
 * The upload page: choose an image of a drawing and the level flow starts.
 *
 * Nothing is uploaded here any more, despite the name. The picture goes into
 * the browser's level store and the visit moves on to colour picking; the
 * server is only asked to keep the image if Save is pressed later, which is the
 * first point at which anyone has said it is worth keeping.
 *
 * Choosing the file is still the only decision on this page, so it moves on by
 * itself — a separate submit button would be a second click that confirms
 * nothing.
 */
import { ref } from "vue";
import { Head, router } from "@inertiajs/vue3";

import AppLayout from "../Layouts/AppLayout.vue";
import { putLevel } from "../levelStore.js";

// The same ceiling the server applies when the level is finally saved. Checking
// it now means a file that could never be kept is refused before it is played.
const MAX_BYTES = 10 * 1024 * 1024;

const error = ref("");
const busy = ref(false);

async function choose(event) {
    const [file] = event.target.files;

    // Clear the native input straight away: after a rejected file the likely
    // next step is picking the same one again after fixing it, and an input
    // that still holds the old value never fires change for the same choice.
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

/** The reason this file cannot become a level, or an empty string if it can. */
async function unusable(file) {
    // SVG is refused for the same reason the server refuses it: it can carry
    // scripts, so it is not an image in the safe sense.
    if (file.type === "image/svg+xml") {
        return "SVG files cannot be used as levels. Try a PNG or a JPG.";
    }

    if (file.size > MAX_BYTES) {
        return "That image is larger than 10 MB.";
    }

    // The real requirement is that the browser can decode it, since the game
    // reads the pixels itself. That is a truer test than trusting the file's
    // type, and it catches a truncated download as well as a renamed PDF.
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

            <!--
                The label is the whole control: the input sits inside it, so a
                click anywhere in the block opens the file picker, and keyboard
                focus on the hidden input shows up as focus-within on the block.
            -->
            <label
                class="mt-8 flex flex-col items-center justify-center gap-3 border-2 border-dashed border-sub px-6 py-24 text-center focus-within:border-ink"
                :class="busy ? 'opacity-50' : 'cursor-pointer hover:border-ink'"
            >
                <!--
                    Disabled while a file is being read: a disabled input does
                    not open the picker, so a second file cannot be chosen
                    halfway through the first.
                -->
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
