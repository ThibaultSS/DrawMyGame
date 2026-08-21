<script setup>
/**
 * The about page: who made DrawMyGame and what it is built with.
 *
 * Laid out exactly like the home page — the same banner treatment, the same
 * container, and the same alternating image/text sections driven by a
 * SECTIONS array — so the two pages are the same page with different words.
 */
import { Head, Link } from "@inertiajs/vue3";

import AppLayout from "../Layouts/AppLayout.vue";
import ScrollCue from "../Components/ScrollCue.vue";

/**
 * The banner artwork. Setting `image` back to null falls the banner back to a
 * plain black band with a visible heading.
 *
 * The alt is empty on purpose: the heading below already names the site to a
 * screen reader, so announcing the logo as well would say it twice.
 */
const BANNER = {
    image: "/assets/DrawMyGame_Logo_Lang.jpg",
    alt: ""
};

// The same shape the home page uses, so the alternating side is one class
// binding rather than two hand-mirrored blocks of markup.
const SECTIONS = [
    {
        title: "About the website",
        image: "/assets/KDG-logo.png",
        alt: "KdG University of Applied Sciences and Arts logo",
        paragraphs: [
            "DrawMyGame is a web application that transforms simple drawings into playable platform games. Instead of building levels with complex editors or coding mechanics by hand, users can sketch platforms, goals and characters using colours and instantly generate a game.",
            "The project combines image processing, physics simulation and game development to make level creation accessible to anyone. Whether you are experimenting with game design or simply having fun, DrawMyGame turns creativity into gameplay within seconds."
        ]
    },
    {
        title: "The technology behind it",
        image: "/assets/Phaser_Logo.png",
        alt: "Phaser logo",
        paragraphs: [
            "DrawMyGame is built using Laravel for the web application and Phaser for the game engine. Uploaded images are processed pixel by pixel to identify shapes based on user-selected colours. These shapes are converted into physics bodies, allowing platforms, goals, players and hazards to interact naturally within the game.",
            "This approach demonstrates how image processing techniques can be combined with modern web technologies to create dynamic and interactive content directly from user-created artwork."
        ]
    }
];
</script>

<template>
    <Head title="About" />

    <AppLayout>

        <!--
            Built like the home page's hero, minus the video. bg-ink is the
            ground rather than a fallback: the logo is a contained panel sitting
            on it, not a picture stretched across it.
        -->
        <section class="relative flex min-h-[calc(100svh_-_5rem)] items-center justify-center overflow-hidden bg-ink">

            <div class="relative flex w-full max-w-6xl flex-col items-center gap-8 px-6 py-24 text-center text-page">

                <!--
                    The logo already says the site's name, so a matching heading
                    beside it would print it twice. The heading stays in the
                    markup for document structure and only leaves the screen.
                -->
                <h1 :class="BANNER.image ? 'sr-only' : 'text-4xl font-semibold tracking-tight md:text-5xl'">
                    About DrawMyGame
                </h1>

                <!--
                    Black lettering on white with no transparency, so it sits in
                    a white panel rather than directly on the black band — the
                    same framing the two logos further down the page use, which
                    is what makes the white ground read as deliberate.

                    max-w-xl is below the file's own 891 px, so the logo is only
                    ever scaled down, never stretched and softened.
                -->
                <div v-if="BANNER.image" class="bg-page p-8 md:p-12">
                    <img
                        :src="BANNER.image"
                        :alt="BANNER.alt"
                        class="w-full max-w-xl"
                    >
                </div>

                <p class="max-w-xl text-lg">
                    A bachelor's project that turns a drawing into a game you can play.
                </p>

            </div>

            <ScrollCue target="page-content" />
        </section>

        <div id="page-content" class="mx-auto flex w-full max-w-6xl flex-col gap-20 px-6 py-16">

            <section class="max-w-2xl">
                <p class="text-lg">
                    I am a student at KdG University of Applied Sciences and Arts in Hoboken,
                    where I study Multimedia &amp; Creative Technologies. DrawMyGame was
                    developed as my bachelor's project and represents the skills and knowledge
                    I have gained throughout my studies. It combines web development, game
                    development and image processing into a single interactive experience,
                    making it a project I am particularly proud of.
                </p>

                <Link href="/upload" class="mt-8 inline-block bg-ink px-4 py-2 text-page">
                    Try it out!
                </Link>
            </section>

            <section
                v-for="(section, index) in SECTIONS"
                :key="section.title"
                class="flex flex-col gap-8 md:flex-row md:items-center md:gap-12"
                :class="{ 'md:flex-row-reverse': index % 2 === 1 }"
            >
                <!--
                    A logo is not a photograph, so unlike the home page's images
                    it sits centred in a padded frame rather than filling it.
                    Both frames share a height so the two sections line up.

                    Phaser_Logo.png is a 3 MB PNG drawn at 128 px tall, which is
                    why these load lazily rather than competing with the banner.
                -->
                <div class="flex min-h-64 w-full items-center justify-center border border-sub p-8 md:w-1/2">
                    <img
                        :src="section.image"
                        :alt="section.alt"
                        class="max-h-32 w-full max-w-xs object-contain"
                        loading="lazy"
                        decoding="async"
                    >
                </div>

                <div class="md:w-1/2">
                    <h2 class="text-2xl font-semibold tracking-tight">{{ section.title }}</h2>

                    <p
                        v-for="paragraph in section.paragraphs"
                        :key="paragraph"
                        class="mt-4"
                    >
                        {{ paragraph }}
                    </p>
                </div>
            </section>

        </div>

    </AppLayout>
</template>
