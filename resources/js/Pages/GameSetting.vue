<script setup>
/**
 * The colour-picking step between uploading a drawing and playing it: arm one of
 * the four roles, then click that colour on the photo. The eyedropper logic is
 * ported straight from the old Blade page's inline script — draw the image at
 * its natural size on an offscreen canvas and read the clicked pixel — because
 * it worked; only the four copy-pasted role blocks became one array.
 *
 * The one behavioural change is the Start Game button: it stays disabled until
 * all four colours are picked. The old page happily posted an empty form and the
 * game then silently broke, because the engine had no colours to match against.
 */
import { computed, nextTick, onMounted, onUnmounted, ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";

import { detectRoleIssues, listOfRoles } from "../game/roleCheck.js";

import AppLayout from "../Layouts/AppLayout.vue";
import { getLevel } from "../levelStore.js";

const props = defineProps({
    // Only a replayed drawing arrives with a server-side image. A level that
    // was just uploaded or drawn is held by the browser and never posted, so
    // for those this is null and the picture comes out of the level store.
    image: {
        type: String,
        default: null
    }
});

const imageUrl = ref(props.image);

// Set only when this page created the URL, so only this page revokes it.
let objectUrl = null;

onMounted(async () => {
    if (imageUrl.value) {
        return;
    }

    const blob = await getLevel();

    // No level on the server and none in the browser: the flow was entered
    // sideways, or the store was cleared. Start over rather than show a
    // broken image the eyedropper would throw on.
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

// One entry per thing a colour can mean in the game. The form field for a role
// is always `${key}Color`, which is exactly what /start-game expects.
// Three of these make a level: something to stand on, somewhere to get to and
// someone to move. Hazards are optional — a level with nothing dangerous in it
// is still a level, and demanding one meant inventing a danger to get past
// this page.
const ROLES = [
    { key: "platform", label: "Pick Platform", name: "platform", required: true },
    { key: "goal", label: "Pick Goal", name: "goal", required: true },
    { key: "player", label: "Pick Player", name: "player", required: true },
    { key: "hazard", label: "Pick Hazard", name: "hazard", required: false }
];

// The role the next click on the image will colour. Null until a button is
// clicked, so a stray click on the photo does nothing.
const currentSelection = ref(null);

const preview = ref(null);

// Set when the engine would not find one of the roles in this photo. Cleared
// on every attempt, so a fixed pick does not keep showing an old complaint.
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

// The disabled button makes a rejected submit unlikely, but the server still
// validates; if it does say no, the reason has to be visible somewhere.
const firstError = computed(
    () => ROLES.map((role) => form.errors[`${role.key}Color`]).find(Boolean)
);

function pickColor(event) {
    if (! currentSelection.value) {
        return;
    }

    const image = preview.value;
    const ctx = photoContext();

    // The click lands in displayed coordinates; scale it back up to the
    // original image's pixel grid before sampling.
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

/**
 * The photo on an offscreen canvas at its true size.
 *
 * naturalWidth/naturalHeight, not the displayed size: both the eyedropper and
 * the check have to work on the original pixels, because that is the file the
 * engine will read.
 */
function photoContext() {
    const image = preview.value;
    const canvas = document.createElement("canvas");
    const ctx = canvas.getContext("2d");

    canvas.width = image.naturalWidth;
    canvas.height = image.naturalHeight;
    ctx.drawImage(image, 0, 0);

    return ctx;
}

/**
 * Runs the engine's own detector over the photo before starting.
 *
 * This is where the failure used to be silent: colours that never resolve into
 * shapes produced a level with no player in it and no explanation. The drawing
 * page has always checked this; the photo page did not.
 *
 * The wording differs from the drawing page's on purpose. There, "too small"
 * means you drew a small mark. Here you picked a colour out of a photograph, so
 * the useful advice is about the colour and the area, not about drawing.
 */
async function submit() {
    validationError.value = "";
    checking.value = true;

    // A phone photo is a full detector pass over several megapixels, so let the
    // button repaint as "Checking…" before the main thread is taken.
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

    // For a photo the two lists are the same problem: the colour is there,
    // since it was picked out of this very image, but nothing big or solid
    // enough came of it.
    const unfound = [...missing, ...tooSmall];

    if (unfound.length > 0) {
        validationError.value =
            `The game could not find ${listOfRoles(unfound)} in this photo. Pick a bolder colour, or one that covers a larger area — small or faint marks are ignored.`;

        return;
    }

    form
        .transform((data) => ({
            ...data,
            // An unpicked hazard means "no hazards", not a colour of "". The
            // empty-string middleware would do this too, but a page should not
            // need a middleware to make its own payload valid.
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

                    <!--
                        The one inline style on the page: the swatch shows a colour
                        the user picked out of their own photo, which by definition
                        is not in the palette.
                    -->
                    <span
                        class="size-10 border border-sub"
                        :style="form[`${role.key}Color`] ? { backgroundColor: form[`${role.key}Color`] } : null"
                    ></span>

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
                <!-- Disabled until every role has a colour: see the header comment. -->
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
