<script setup>
/**
 * The upload page: choose an image of a drawing and the level flow starts.
 *
 * The old Blade page submitted the form the moment a file was chosen, and that
 * behaviour is kept on purpose: picking the file is the only decision on this
 * page, so a separate submit button would be a second click that confirms
 * nothing. The form posts to /upload-level, the server redirects to the
 * colour-picking page, and Inertia follows the redirect — there is nothing to
 * handle on success here.
 */
import { Head, useForm } from "@inertiajs/vue3";

import AppLayout from "../Layouts/AppLayout.vue";

const form = useForm({
    levelImage: null
});

function upload(event) {
    const [file] = event.target.files;

    if (! file) {
        return;
    }

    form.levelImage = file;

    // Clear the native input before posting. If the server rejects the file,
    // the likely next step is picking the same file again after fixing it, and
    // a file input that still holds the old value never fires change for the
    // same choice.
    event.target.value = "";

    // useForm sees the File and sends the request as multipart by itself.
    form.post("/upload-level");
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
                :class="form.processing ? 'opacity-50' : 'cursor-pointer hover:border-ink'"
            >
                <!--
                    Disabled while uploading: a disabled input does not open the
                    picker, so a second file cannot be chosen mid-upload.
                -->
                <input
                    type="file"
                    accept="image/*"
                    class="sr-only"
                    :disabled="form.processing"
                    @change="upload"
                >

                <span class="text-lg font-medium">
                    Choose an image of your drawing
                </span>

                <span v-if="form.processing" class="text-sm" role="status">
                    Uploading…
                    <!-- percentage can be briefly unknown; a bare "%" reads as broken. -->
                    <template v-if="form.progress?.percentage != null">{{ form.progress.percentage }}%</template>
                </span>

                <span v-else class="text-sm">
                    The upload starts as soon as you pick one.
                </span>
            </label>

            <p v-if="form.errors.levelImage" class="mt-3 text-sm text-error" role="alert">
                {{ form.errors.levelImage }}
            </p>

        </div>
    </AppLayout>
</template>
