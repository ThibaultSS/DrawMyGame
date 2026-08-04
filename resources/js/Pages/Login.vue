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

const FIELD_CLASS =
    "w-full border border-sub px-3 py-2 outline-none focus:border-ink";
</script>

<template>
    <Head title="Log in" />

    <AppLayout>
        <div class="grid min-h-[32rem] grid-cols-1 md:grid-cols-2">

            <!-- Left panel. Hidden on small screens: the form matters, this does not. -->
            <aside class="hidden bg-ink px-10 py-16 text-page md:flex md:flex-col md:justify-center">
                <p class="text-3xl font-semibold tracking-tight">DrawMyGame</p>
                <p class="mt-4 text-lg">Draw a level.<br>Play it.</p>
            </aside>

            <section class="flex flex-col justify-center px-6 py-12 md:px-12">
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
                                required
                                :class="FIELD_CLASS"
                                class="mt-1"
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
                                :class="FIELD_CLASS"
                                class="mt-1"
                            >
                        </div>

                        <!--
                            One message for every failure. The server deliberately does not
                            say whether it was the email or the password that was wrong.
                        -->
                        <p v-if="loginForm.errors.email" class="text-sm" role="alert">
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
                                :class="FIELD_CLASS"
                                class="mt-1"
                            >
                            <p v-if="registerForm.errors.username" class="mt-1 text-sm" role="alert">
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
                                :class="FIELD_CLASS"
                                class="mt-1"
                            >
                            <p v-if="registerForm.errors.email" class="mt-1 text-sm" role="alert">
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
                                :class="FIELD_CLASS"
                                class="mt-1"
                            >
                            <p v-if="registerForm.errors.password" class="mt-1 text-sm" role="alert">
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
                                :class="FIELD_CLASS"
                                class="mt-1"
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
