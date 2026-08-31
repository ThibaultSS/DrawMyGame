<script setup>
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
        <div class="flex min-h-[32rem] flex-col items-center justify-center px-6 py-16">

            <section class="w-full max-w-md border border-sub p-8">
                <div class="w-full">

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

                    <a
                        href="/auth/google"
                        class="mt-4 flex w-full items-center justify-center gap-3 border border-sub px-4 py-2"
                    >
                        <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
                            <path fill="#4285F4" d="M45.1 24.5c0-1.6-.1-3.1-.4-4.5H24v8.5h11.8c-.5 2.7-2 5-4.4 6.6v5.5h7.1c4.2-3.8 6.6-9.5 6.6-16.1z" />
                            <path fill="#34A853" d="M24 46c6 0 11-2 14.6-5.4l-7.1-5.5c-2 1.3-4.5 2.1-7.5 2.1-5.8 0-10.7-3.9-12.400-9.1H4.2v5.7C7.8 41.1 15.3 46 24 46z" />
                            <path fill="#FBBC05" d="M11.6 28.1c-.5-1.3-.7-2.7-.7-4.1s.3-2.8.7-4.1v-5.7H4.2C2.8 17 2 20.4 2 24s.8 7 2.2 9.8l7.4-5.7z" />
                            <path fill="#EA4335" d="M24 10.4c3.3 0 6.2 1.1 8.5 3.3l6.3-6.3C35 3.9 30 2 24 2 15.3 2 7.8 6.9 4.2 14.2l7.4 5.7c1.7-5.2 6.6-9.5 12.4-9.5z" />
                        </svg>
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
