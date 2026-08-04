<script setup>
/**
 * The landing page: a hero that sends people to /upload, then three sections
 * that walk through the loop — draw a level, photograph it, play it.
 *
 * The Blade version opened with a banner video under an overlay and framed
 * every image and button with animated PNG borders swapped by setInterval.
 * None of that is carried over: the migrated pages are typographic, so this
 * one is the copy, three images, and a single button.
 */
import { Head, Link } from "@inertiajs/vue3";

import AppLayout from "../Layouts/AppLayout.vue";

// The three how-it-works sections, as data: the alternating image side is
// then one class binding instead of three hand-mirrored blocks of markup.
const SECTIONS = [
    {
        title: "Start Drawing!",
        image: "/assets/pencils.jpg",
        alt: "Coloured pencils",
        paragraphs: [
            "Grab a piece of paper and let your creativity run wild! Draw your own platformer level and watch it come to life in the game.",
            "Use 4 contrasting colours, for instance red, yellow, blue and black. The bolder and cleaner your colours are, the better the game will work, so don't be shy with that marker.",
            "Every level needs four things. First, draw your platforms, these are the surfaces your player will run and jump on. Think rectangles, stairs, floating islands, whatever comes to mind. Second, draw a goal like a flag, door or star. This is where your player needs to reach to win the level. Third, spice things up with some hazards like spikes or lava that the player needs to avoid. And finally, draw your player character somewhere on the level, a simple circle or blob is more than enough.",
            "You can also do this in Paint, Illustrator or whatever drawing tool you use."
        ]
    },
    {
        title: "Take a picture",
        image: "/assets/picture-phone.png",
        alt: "A phone taking a picture of a drawing",
        paragraphs: [
            "Once you are happy with your drawing, it is time to bring it into the game. Start by taking a clear photo of your level. Make sure you are in a well-lit room and crank up the brightness on your phone or camera for the best result. Try to hold your phone directly above the drawing and keep it as flat and straight as possible. The less distortion, the better the game will read your colours.",
            "Once you have your photo, head over to the upload page and select your file. The game accepts all common image formats including PNG, JPG and JPEG. After uploading, you will be taken to the colour selection screen where you map each colour in your drawing to its role in the game. From there, hit start and your level is ready to play."
        ]
    },
    {
        title: "Upload & Play!",
        image: "/assets/Platform.png",
        alt: "A drawn platformer level",
        paragraphs: [
            "Use the arrow keys to move your character and jump your way to the goal while avoiding any hazards you drew along the way. Every level is unique because you made it, the shapes, the layout, all of it comes straight from your imagination.",
            "Want to try something different? No problem. You can upload as many levels as you want and even save your favorites to your account to replay them whenever you want. So keep drawing, keep experimenting and most importantly, enjoy the experience!"
        ]
    }
];
</script>

<template>
    <Head title="Home" />

    <AppLayout>

        <!-- The hero: what the banner video used to say, in plain type. -->
        <section class="border-b border-sub">
            <div class="mx-auto flex w-full max-w-6xl flex-col items-center gap-6 px-6 py-20 text-center">

                <h1 class="text-4xl font-semibold tracking-tight">Upload your drawing!</h1>

                <p class="max-w-xl text-lg">
                    Draw your own picture and play it! Make platforms, your own
                    characters and deadly spikes. Your creativity is the limit.
                    Try it now!
                </p>

                <Link href="/upload" class="bg-ink px-6 py-2 text-page">
                    Upload
                </Link>

            </div>
        </section>

        <div class="mx-auto flex w-full max-w-6xl flex-col gap-20 px-6 py-16">

            <section
                v-for="(section, index) in SECTIONS"
                :key="section.title"
                class="flex flex-col gap-8 md:flex-row md:items-center md:gap-12"
                :class="{ 'md:flex-row-reverse': index % 2 === 1 }"
            >
                <img
                    :src="section.image"
                    :alt="section.alt"
                    class="w-full border border-sub md:w-1/2"
                >

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
