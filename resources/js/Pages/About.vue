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
            The black is a 5 px frame, not a ground: p-[5px] on the section is
            the whole of it, and the white panel below fills everything inside.

            The panel has to be flex-1 rather than centred content, or the
            section's height reappears as black above and below it — which is
            where that black came from before, not from padding.
        -->
        <section class="relative flex min-h-[calc(100svh_-_5rem)] flex-col overflow-hidden bg-ink p-[5px]">

            <div class="relative flex flex-1 flex-col items-center justify-center gap-8 bg-page px-8 py-16 text-center text-ink">

                <!--
                    The logo already says the site's name, so a matching heading
                    beside it would print it twice. The heading stays in the
                    markup for document structure and only leaves the screen.
                -->
                <h1 :class="BANNER.image ? 'sr-only' : 'text-4xl font-semibold tracking-tight md:text-5xl'">
                    About DrawMyGame
                </h1>

                <!--
                    max-w-4xl is 896 px against the file's own 891 px, so the
                    logo sits at roughly its true size however wide the window
                    gets. Without the cap it would stretch the full width of the
                    panel and go soft.
                -->
                <img
                    v-if="BANNER.image"
                    :src="BANNER.image"
                    :alt="BANNER.alt"
                    class="w-full max-w-4xl"
                >

                <p class="max-w-xl text-lg">
                    A bachelor's project that turns a drawing into a game you can play.
                </p>

            </div>

            <!-- Black, because it is sitting on the white panel now. -->
            <ScrollCue target="page-content" on-light />
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
