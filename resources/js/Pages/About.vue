<script setup>
/**
 * The about page: who made DrawMyGame and what it is built with.
 *
 * The content is the same as the old Blade page, but the presentation is not.
 * The banner image, the animated PNG photo frames and the frame-swapping
 * interval are gone on purpose: this is a reading page, so it is typography
 * first — an intro, then two sections that alternate a logo and a block of
 * text. The logos sit in plain border-sub frames on the white page, which is
 * all the framing the house style asks for.
 */
import { Head, Link } from "@inertiajs/vue3";

import AppLayout from "../Layouts/AppLayout.vue";
</script>

<template>
    <Head title="About" />

    <AppLayout>
        <div class="mx-auto w-full max-w-6xl px-6 py-12">

            <section class="max-w-2xl">
                <h1 class="text-3xl font-semibold tracking-tight">About</h1>

                <p class="mt-4">
                    I am a student at KdG University of Applied Sciences and Arts in Hoboken,
                    where I study Multimedia &amp; Creative Technologies. DrawMyGame was
                    developed as my bachelor's project and represents the skills and knowledge
                    I have gained throughout my studies. It combines web development, game
                    development and image processing into a single interactive experience,
                    making it a project I am particularly proud of.
                </p>

                <Link href="/upload" class="mt-6 inline-block bg-ink px-4 py-2 text-page">
                    Try it out!
                </Link>
            </section>

            <!--
                The text comes first in the DOM so a phone reads heading, then
                paragraphs, then logo. md:order-first only moves the logo to the
                left column on desktop, which alternates it with the section below.
            -->
            <section class="mt-16 grid grid-cols-1 items-center gap-8 md:grid-cols-2 md:gap-12">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight">About the website</h2>

                    <p class="mt-4">
                        DrawMyGame is a web application that transforms simple drawings into
                        playable platform games. Instead of building levels with complex
                        editors or coding mechanics by hand, users can sketch platforms,
                        goals and characters using colours and instantly generate a game.
                    </p>

                    <p class="mt-4">
                        The project combines image processing, physics simulation and game
                        development to make level creation accessible to anyone. Whether you
                        are experimenting with game design or simply having fun, DrawMyGame
                        turns creativity into gameplay within seconds.
                    </p>
                </div>

                <div class="flex items-center justify-center border border-sub p-8 md:order-first">
                    <img
                        src="/assets/KDG-logo.png"
                        alt="KdG University of Applied Sciences and Arts logo"
                        class="max-h-32 w-full max-w-xs object-contain"
                    >
                </div>
            </section>

            <section class="mt-16 grid grid-cols-1 items-center gap-8 md:grid-cols-2 md:gap-12">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight">The technology behind it</h2>

                    <p class="mt-4">
                        DrawMyGame is built using Laravel for the web application and Phaser
                        for the game engine. Uploaded images are processed pixel by pixel to
                        identify shapes based on user-selected colours. These shapes are
                        converted into physics bodies, allowing platforms, goals, players and
                        hazards to interact naturally within the game.
                    </p>

                    <p class="mt-4">
                        This approach demonstrates how image processing techniques can be
                        combined with modern web technologies to create dynamic and
                        interactive content directly from user-created artwork.
                    </p>
                </div>

                <div class="flex items-center justify-center border border-sub p-8">
                    <img
                        src="/assets/Phaser_Logo.png"
                        alt="Phaser logo"
                        class="max-h-32 w-full max-w-xs object-contain"
                    >
                </div>
            </section>

        </div>
    </AppLayout>
</template>
