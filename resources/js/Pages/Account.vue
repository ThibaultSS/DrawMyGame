<script setup>
/**
 * The saved drawings, with publish and delete.
 *
 * Publishing used to be a single toggle. It now asks for a title and, if you
 * want one, a description — a card in the gallery with neither says nothing
 * about what the level is. The form opens inside the card rather than in a
 * dialog: there is no focus trap or escape handling to get wrong, and it keeps
 * the page working the way the rest of the app does.
 *
 * Every action returns back(), so Inertia re-fetches the props and only the
 * card that changed re-renders; the layout's FlashToast shows the outcome.
 */
import { computed, ref } from "vue";
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";

import AppLayout from "../Layouts/AppLayout.vue";
import Pagination from "../Components/Pagination.vue";

defineProps({
    // A Laravel paginator: the cards live in drawings.data, the page links in
    // drawings.links.
    drawings: {
        type: Object,
        required: true
    },
    // Levels other people made that you kept. A separate paginator with its own
    // page name, so paging one list does not page the other.
    favourites: {
        type: Object,
        required: true
    }
});

/* ------------------------------------------------------------------ *
 * The account itself
 * ------------------------------------------------------------------ */

const user = computed(() => usePage().props.auth.user);

// Kept closed by default: this page is for the drawings, and the three forms
// below are a rare visit rather than the point of it.
const showSettings = ref(false);

const usernameForm = useForm({ username: user.value.username });

const passwordForm = useForm({
    current_password: "",
    password: "",
    password_confirmation: ""
});

// Confirmed by typing the username rather than the password: people who signed
// in with Google never set one, and asking for it would leave them unable to
// delete their own account.
const deleteForm = useForm({ username: "" });

// A POST, not a link: logging out changes state, and Inertia sends the CSRF
// token for us. The route ends in Inertia::location, so the browser lands on
// the home page with a full visit.
function logout() {
    router.post("/logout");
}

function saveUsername() {
    usernameForm.patch("/account/username", { preserveScroll: true });
}

function savePassword() {
    passwordForm.patch("/account/password", {
        preserveScroll: true,
        // Never leave passwords sitting in the page after a successful change.
        onSuccess: () => passwordForm.reset()
    });
}

function deleteAccount() {
    if (! window.confirm("Delete your account? Levels you published stay in the community, without your name.")) {
        return;
    }

    deleteForm.delete("/account", { preserveScroll: true });
}

/* ------------------------------------------------------------------ *
 * The drawings
 * ------------------------------------------------------------------ */

// Ids currently being changed, so a card's buttons can be disabled while its
// request is in flight and a double click cannot fire the action twice.
const busy = ref(new Set());

function isBusy(id) {
    return busy.value.has(id);
}

function markBusy(id, running) {
    const next = new Set(busy.value);

    running ? next.add(id) : next.delete(id);
    busy.value = next;
}

/* ------------------------------------------------------------------ *
 * Publishing
 * ------------------------------------------------------------------ */

// Which card has its form open. Only ever one, so a single form is enough.
const editingId = ref(null);

const form = useForm({
    title: "",
    description: ""
});

function startEditing(drawing) {
    editingId.value = drawing.id;

    // Prefilled, so editing a published level starts from what it already says
    // rather than from an empty box.
    form.title = drawing.title ?? "";
    form.description = drawing.description ?? "";
    form.clearErrors();
}

function cancelEditing() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

// Publishing and editing the details are the same request: "publish it, with
// this text" describes both, so there is no second endpoint for the edit.
function publish(drawing) {
    form.post(`/drawing/${drawing.id}/publish`, {
        preserveScroll: true,
        onSuccess: () => cancelEditing()
    });
}

function unpublish(drawing) {
    router.post(`/drawing/${drawing.id}/unpublish`, {}, {
        preserveScroll: true,
        onStart: () => markBusy(drawing.id, true),
        onFinish: () => markBusy(drawing.id, false)
    });
}

function destroy(drawing) {
    // A native confirm for now. It is the one thing standing between a stray
    // click and a deleted drawing, so it is worth having before it is pretty.
    if (! window.confirm("Delete this drawing? This cannot be undone.")) {
        return;
    }

    router.delete(`/drawing/${drawing.id}`, {
        preserveScroll: true,
        onStart: () => markBusy(drawing.id, true),
        onFinish: () => markBusy(drawing.id, false)
    });
}

/**
 * A field in error turns red, border and message both — the same treatment the
 * login form gives, because on an otherwise black-on-white page an error in
 * black reads as another label.
 */
function fieldClass(error) {
    return [
        "w-full border px-3 py-2 outline-none",
        error ? "border-error" : "border-sub focus:border-ink"
    ];
}
</script>

<template>
    <Head title="My drawings" />

    <AppLayout>
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-8 px-6 py-12">

            <section class="flex flex-col gap-4 border border-sub p-4">

                <div class="flex flex-wrap items-center justify-between gap-4">
                    <p>Signed in as <span class="font-medium">{{ user.username }}</span></p>

                    <div class="flex gap-2 text-sm">
                        <button
                            type="button"
                            class="px-3 py-1.5"
                            :class="showSettings ? 'bg-ink text-page' : 'border border-sub'"
                            :aria-expanded="showSettings"
                            @click="showSettings = ! showSettings"
                        >
                            Settings
                        </button>

                        <button type="button" class="border border-sub px-3 py-1.5" @click="logout">
                            Log out
                        </button>
                    </div>
                </div>

                <div v-if="showSettings" class="grid grid-cols-1 gap-8 border-t border-sub pt-4 md:grid-cols-3">

                    <form class="flex flex-col gap-2" @submit.prevent="saveUsername">
                        <h2 class="font-medium">Username</h2>
                        <p class="text-sm">This is the name on your community levels.</p>

                        <input
                            id="username"
                            v-model="usernameForm.username"
                            type="text"
                            autocomplete="username"
                            required
                            :class="fieldClass(usernameForm.errors.username)"
                            :aria-invalid="Boolean(usernameForm.errors.username)"
                        >
                        <p v-if="usernameForm.errors.username" class="text-sm text-error" role="alert">
                            {{ usernameForm.errors.username }}
                        </p>

                        <button
                            type="submit"
                            class="self-start bg-ink px-3 py-1.5 text-sm text-page disabled:opacity-50"
                            :disabled="usernameForm.processing"
                        >
                            Save username
                        </button>
                    </form>

                    <form class="flex flex-col gap-2" @submit.prevent="savePassword">
                        <h2 class="font-medium">Password</h2>
                        <!-- Google accounts were given a random password nobody
                             knows, so this form is not usable by them. -->
                        <p class="text-sm">Not available if you sign in with Google.</p>

                        <label class="text-sm" for="current-password">Current password</label>
                        <input
                            id="current-password"
                            v-model="passwordForm.current_password"
                            type="password"
                            autocomplete="current-password"
                            required
                            :class="fieldClass(passwordForm.errors.current_password)"
                        >
                        <p v-if="passwordForm.errors.current_password" class="text-sm text-error" role="alert">
                            {{ passwordForm.errors.current_password }}
                        </p>

                        <label class="text-sm" for="new-password">New password</label>
                        <input
                            id="new-password"
                            v-model="passwordForm.password"
                            type="password"
                            autocomplete="new-password"
                            required
                            :class="fieldClass(passwordForm.errors.password)"
                        >

                        <label class="text-sm" for="confirm-password">Confirm new password</label>
                        <input
                            id="confirm-password"
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            required
                            :class="fieldClass(passwordForm.errors.password)"
                        >
                        <p v-if="passwordForm.errors.password" class="text-sm text-error" role="alert">
                            {{ passwordForm.errors.password }}
                        </p>

                        <button
                            type="submit"
                            class="self-start bg-ink px-3 py-1.5 text-sm text-page disabled:opacity-50"
                            :disabled="passwordForm.processing"
                        >
                            Change password
                        </button>
                    </form>

                    <form class="flex flex-col gap-2" @submit.prevent="deleteAccount">
                        <h2 class="font-medium">Delete account</h2>
                        <p class="text-sm">
                            Your unpublished drawings go with it. Levels you published stay in the
                            community, credited to an unknown publisher.
                        </p>

                        <label class="text-sm" for="confirm-username">
                            Type <span class="font-medium">{{ user.username }}</span> to confirm
                        </label>
                        <input
                            id="confirm-username"
                            v-model="deleteForm.username"
                            type="text"
                            autocomplete="off"
                            required
                            :class="fieldClass(deleteForm.errors.username)"
                            :aria-invalid="Boolean(deleteForm.errors.username)"
                        >
                        <p v-if="deleteForm.errors.username" class="text-sm text-error" role="alert">
                            {{ deleteForm.errors.username }}
                        </p>

                        <button
                            type="submit"
                            class="self-start border border-error px-3 py-1.5 text-sm text-error disabled:opacity-50"
                            :disabled="deleteForm.processing"
                        >
                            Delete my account
                        </button>
                    </form>

                </div>
            </section>

            <h1 class="text-2xl font-semibold tracking-tight">My drawings</h1>

            <div v-if="drawings.data.length === 0" class="flex flex-col items-start gap-4">
                <p>
                    Nothing saved yet. Photograph a drawing or draw one here, and
                    pressing Save while you play it puts it in this list.
                </p>

                <div class="flex flex-wrap gap-4">
                    <Link href="/upload" class="bg-ink px-4 py-2 text-page">Upload a drawing</Link>
                    <Link href="/draw" class="border border-sub px-4 py-2">Draw one here</Link>
                </div>
            </div>

            <template v-else>
                <ul class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <li
                        v-for="drawing in drawings.data"
                        :key="drawing.id"
                        class="flex flex-col gap-3 border border-sub p-3"
                    >
                        <Link :href="`/play/${drawing.id}`" class="block">
                            <img
                                :src="drawing.image"
                                alt="Saved drawing"
                                class="aspect-4/3 w-full object-cover"
                            >
                        </Link>

                        <p class="text-sm font-medium">
                            {{ drawing.title || "Untitled" }}
                        </p>

                        <!-- The form takes the place of the buttons, in the card. -->
                        <form
                            v-if="editingId === drawing.id"
                            class="flex flex-col gap-2"
                            @submit.prevent="publish(drawing)"
                        >
                            <div>
                                <label class="block text-sm" :for="`title-${drawing.id}`">Title</label>
                                <input
                                    :id="`title-${drawing.id}`"
                                    v-model="form.title"
                                    type="text"
                                    maxlength="80"
                                    autofocus
                                    required
                                    :class="fieldClass(form.errors.title)"
                                    :aria-invalid="Boolean(form.errors.title)"
                                >
                                <p v-if="form.errors.title" class="mt-1 text-sm text-error" role="alert">
                                    {{ form.errors.title }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm" :for="`description-${drawing.id}`">
                                    Description <span class="text-sm">(optional)</span>
                                </label>
                                <textarea
                                    :id="`description-${drawing.id}`"
                                    v-model="form.description"
                                    rows="3"
                                    maxlength="500"
                                    :class="fieldClass(form.errors.description)"
                                    :aria-invalid="Boolean(form.errors.description)"
                                ></textarea>
                                <p v-if="form.errors.description" class="mt-1 text-sm text-error" role="alert">
                                    {{ form.errors.description }}
                                </p>
                            </div>

                            <div class="flex gap-2">
                                <button
                                    type="submit"
                                    class="bg-ink px-3 py-1.5 text-sm text-page disabled:opacity-50"
                                    :disabled="form.processing"
                                >
                                    {{ drawing.published ? "Save details" : "Publish" }}
                                </button>

                                <button
                                    type="button"
                                    class="border border-sub px-3 py-1.5 text-sm"
                                    @click="cancelEditing"
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>

                        <div v-else class="flex items-center justify-between gap-3">
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="px-3 py-1.5 text-sm disabled:opacity-50"
                                    :class="drawing.published ? 'bg-ink text-page' : 'border border-sub'"
                                    :disabled="isBusy(drawing.id)"
                                    @click="startEditing(drawing)"
                                >
                                    {{ drawing.published ? "Edit details" : "Publish" }}
                                </button>

                                <button
                                    v-if="drawing.published"
                                    type="button"
                                    class="border border-sub px-3 py-1.5 text-sm disabled:opacity-50"
                                    :disabled="isBusy(drawing.id)"
                                    @click="unpublish(drawing)"
                                >
                                    Unpublish
                                </button>
                            </div>

                            <button
                                type="button"
                                class="px-3 py-1.5 text-sm text-error disabled:opacity-50"
                                :disabled="isBusy(drawing.id)"
                                @click="destroy(drawing)"
                            >
                                Delete
                            </button>
                        </div>
                    </li>
                </ul>

                <Pagination :links="drawings.links" />
            </template>

            <!-- Levels somebody else made. They stay theirs: keeping one only
                 remembers the speed and jump you played it at. -->
            <section class="flex flex-col gap-8">

                <div>
                    <h2 class="text-2xl font-semibold tracking-tight">Saved from others</h2>
                    <p class="mt-2">Levels from the community you kept to play again.</p>
                </div>

                <!-- An empty section said nothing at all before, which reads as
                     a page that has not finished loading. -->
                <div v-if="favourites.data.length === 0" class="flex flex-col items-start gap-4">
                    <p>
                        You have not kept any yet. Saving a level somebody else made
                        leaves it theirs, and remembers the speed and jump you like.
                    </p>

                    <Link href="/community" class="border border-sub px-4 py-2">Browse the community</Link>
                </div>

                <ul v-else class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <li v-for="level in favourites.data" :key="level.id">
                        <Link
                            :href="`/play/${level.id}`"
                            class="flex h-full flex-col gap-3 border border-sub p-3 hover:border-ink"
                        >
                            <!-- The title is right below, so the alt stays generic. -->
                            <img
                                :src="level.image"
                                alt="Level drawing"
                                class="aspect-4/3 w-full object-cover"
                                loading="lazy"
                            >

                            <p class="truncate font-medium">{{ level.title || "Untitled" }}</p>

                            <p class="mt-auto truncate text-sm">By {{ level.author }}</p>
                        </Link>
                    </li>
                </ul>

                <Pagination v-if="favourites.data.length > 0" :links="favourites.links" />

            </section>

        </div>
    </AppLayout>
</template>
