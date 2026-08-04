<script setup>
/**
 * Login and register, split screen: a black panel on the left, the form on the
 * right.
 *
 * Both forms use Inertia's useForm, which is the whole point of the migration
 * here: a failed attempt no longer reloads the page, so validation errors appear
 * without throwing away what was typed. The old Blade form had no old('email'),
 * so a wrong password cleared the email field every time.
 */
import { ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";

import AppLayout from "../Layouts/AppLayout.vue";

const mode = ref("login");

const loginForm = useForm({
    email: "",
    password: ""
});

const registerForm = useForm({
    username: "",
    email: "",
    password: "",
    password_confirmation: ""
});

function submitLogin() {
    loginForm.post("/login", {
        onFinish: () => loginForm.reset("password")
    });
}

function submitRegister() {
    registerForm.post("/register", {
        onFinish: () => registerForm.reset("password", "password_confirmation")
    });
}

/**
 * A field in error turns red, border and message both. Everything else on the
 * page is black on white, so an error rendered in black reads as another label.
 */
function fieldClass(error) {
    return [
        "mt-1 w-full border px-3 py-2 outline-none",
        error ? "border-error" : "border-sub focus:border-ink"
    ];
}
</script>

<template>
    <Head :title="mode === 'login' ? 'Log in' : 'Create an account'" />

    <AppLayout>
        <div class="grid min-h-[32rem] grid-cols-1 md:grid-cols-2">

            <!-- Left panel. Hidden on small screens: the form matters, this does not. -->
            <aside class="hidden bg-ink px-10 py-16 text-page md:flex md:flex-col md:items-center md:justify-center">
                <div class="w-full max-w-sm">
                    <p class="text-3xl font-semibold tracking-tight">DrawMyGame</p>
                    <p class="mt-4 text-lg">Draw a level.<br>Play it.</p>
                </div>
            </aside>

            <!--
                items-center matters: without it the max-w-sm block below stretches
                from the left edge of its half and the whole page reads as shoved
                to one side. Both halves centre their content, so the two blocks
                sit an equal distance from the seam.
            -->
            <section class="flex flex-col items-center justify-center px-6 py-12 md:px-12">
                <div class="w-full max-w-sm">

                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ mode === "login" ? "Log in" : "Create an account" }}
                    </h1>

                    <form v-if="mode === 'login'" class="mt-8 space-y-5" @submit.prevent="submitLogin">

                        <div>
                            <label class="block text-sm" for="login-email">Email</label>
                            <input
                                id="login-email"
                                v-model="loginForm.email"
                                type="email"
                                autocomplete="email"
                                autofocus
                                required
                                :class="fieldClass(loginForm.errors.email)"
                                :aria-invalid="Boolean(loginForm.errors.email)"
                            >
                        </div>

                        <div>
                            <label class="block text-sm" for="login-password">Password</label>
                            <input
                                id="login-password"
                                v-model="loginForm.password"
                                type="password"
                                autocomplete="current-password"
                                required
                                :class="fieldClass(loginForm.errors.email)"
                            >
                        </div>

                        <!--
                            One message for every failure. The server deliberately does not
                            say whether it was the email or the password that was wrong.
                        -->
                        <p v-if="loginForm.errors.email" class="text-sm text-error" role="alert">
                            {{ loginForm.errors.email }}
                        </p>

                        <button
                            type="submit"
                            class="w-full bg-ink px-4 py-2 text-page disabled:opacity-50"
                            :disabled="loginForm.processing"
                        >
                            {{ loginForm.processing ? "Logging in…" : "Log in" }}
                        </button>

                    </form>

                    <form v-else class="mt-8 space-y-5" @submit.prevent="submitRegister">

                        <div>
                            <label class="block text-sm" for="register-username">Username</label>
                            <input
                                id="register-username"
                                v-model="registerForm.username"
                                type="text"
                                autocomplete="username"
                                required
                                :class="fieldClass(registerForm.errors.username)"
                                :aria-invalid="Boolean(registerForm.errors.username)"
                            >
                            <p v-if="registerForm.errors.username" class="mt-1 text-sm text-error" role="alert">
                                {{ registerForm.errors.username }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm" for="register-email">Email</label>
                            <input
                                id="register-email"
                                v-model="registerForm.email"
                                type="email"
                                autocomplete="email"
                                required
                                :class="fieldClass(registerForm.errors.email)"
                                :aria-invalid="Boolean(registerForm.errors.email)"
                            >
                            <p v-if="registerForm.errors.email" class="mt-1 text-sm text-error" role="alert">
                                {{ registerForm.errors.email }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm" for="register-password">Password</label>
                            <input
                                id="register-password"
                                v-model="registerForm.password"
                                type="password"
                                autocomplete="new-password"
                                required
                                :class="fieldClass(registerForm.errors.password)"
                                :aria-invalid="Boolean(registerForm.errors.password)"
                            >
                            <p v-if="registerForm.errors.password" class="mt-1 text-sm text-error" role="alert">
                                {{ registerForm.errors.password }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm" for="register-password-confirm">Confirm password</label>
                            <input
                                id="register-password-confirm"
                                v-model="registerForm.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                required
                                :class="fieldClass(registerForm.errors.password)"
                            >
                        </div>

                        <button
                            type="submit"
                            class="w-full bg-ink px-4 py-2 text-page disabled:opacity-50"
                            :disabled="registerForm.processing"
                        >
                            {{ registerForm.processing ? "Creating account…" : "Create account" }}
                        </button>

                    </form>

                    <div class="mt-8 flex items-center gap-4 text-sm">
                        <span class="h-px flex-1 bg-sub"></span>
                        <span>or</span>
                        <span class="h-px flex-1 bg-sub"></span>
                    </div>

                    <!--
                        A real browser navigation, never an Inertia <Link>: /auth/google
                        redirects off-site to Google, which is not an Inertia response.
                    -->
                    <a
                        href="/auth/google"
                        class="mt-4 flex w-full items-center justify-center gap-3 border border-sub px-4 py-2"
                    >
                        <img
                            src="https://developers.google.com/identity/images/g-logo.png"
                            alt=""
                            width="18"
                            height="18"
                        >
                        Continue with Google
                    </a>

                    <p class="mt-8 text-sm">
                        <template v-if="mode === 'login'">
                            No account?
                            <button type="button" class="underline" @click="mode = 'register'">Register</button>
                        </template>
                        <template v-else>
                            Already have an account?
                            <button type="button" class="underline" @click="mode = 'login'">Log in</button>
                        </template>
                    </p>

                </div>
            </section>

        </div>
    </AppLayout>
</template>
