<script setup>
/**
 * The landing page: a banner video you can read over, then the loop explained
 * at length — draw a level, photograph it, play it, and play everyone else's.
 *
 * The video is decoration, so it is aria-hidden and muted — browsers refuse to
 * autoplay anything with sound anyway, and this file has an audio track. It
 * sits on a black section, so the hero is readable from the first paint
 * whether or not a single frame has arrived yet.
 */
import { Head, Link } from "@inertiajs/vue3";

import AppLayout from "../Layouts/AppLayout.vue";
import ScrollCue from "../Components/ScrollCue.vue";

// A 48-second loop behind the page copy is exactly what this setting is for.
// Without autoplay the video holds still and gains its own controls, so it is
// there for anyone who does want to watch it.
const reduceMotion =
    typeof window !== "undefined" &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;

// The how-it-works sections, as data: the alternating image side is then one
// class binding instead of four hand-mirrored blocks of markup.
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
    },
    {
        title: "Play what others drew",
        image: "/assets/community.png",
        alt: "Two children playing a hand-drawn level on a laptop",
        paragraphs: [
            "You are not the only one drawing. Every level someone decides to share ends up in the community gallery and all of them are free to play. Some are careful little puzzles with platforms placed exactly where they need to be, others are wild scribbles that somehow still work. Head to the community page and you can search through them by title or by the person who made them and sort by the newest levels or by the ones everybody likes most.",
            "Playing someone else's level works exactly like playing your own: same arrow keys, same goal and same spikes to keep away from. The only difference is that you have no idea what is coming, which is half the fun. If a level made you laugh or made you rage quit, you can give it a thumbs up or a thumbs down while you play it. Therefor you need an account and you get only one vote per level, so the rankings mean something."
        ]
    }
];
</script>

<template>
    <Head title="Home" />

    <AppLayout>

        <!--
            The hero. bg-ink is not just a fallback: the video is 6 MB and only
            its metadata is preloaded, so the black ground with white type on it
            is what the first paint actually shows.
        -->
        <section class="relative flex min-h-[calc(100svh_-_5rem)] items-center justify-center overflow-hidden bg-ink">

            <video
                class="absolute inset-0 size-full object-cover"
                src="/assets/banner.mp4"
                muted
                :autoplay="!reduceMotion"
                :controls="reduceMotion"
                loop
                playsinline
                preload="metadata"
                aria-hidden="true"
                tabindex="-1"
            ></video>

            <!-- Without this the copy is only as readable as whatever frame
                 happens to be showing behind it. -->
            <div class="absolute inset-0 bg-ink/50"></div>

            <div class="relative flex w-full max-w-6xl flex-col items-center gap-6 px-6 py-24 text-center text-page">

                <h1 class="text-4xl font-semibold tracking-tight md:text-5xl">
                    Draw it. Play it.
                </h1>

                <p class="max-w-xl text-lg">
                    Turn a hand-drawn picture into a platformer you can play.
                    Draw the platforms, the goal, the spikes and your player. The
                    game builds the rest.
                </p>

                <div class="flex flex-wrap items-center justify-center gap-4">
                    <Link href="/upload" class="bg-page px-6 py-3 text-ink">
                        Upload a drawing
                    </Link>

                    <Link href="/draw" class="border border-page px-6 py-3 text-page">
                        Draw one here
                    </Link>
                </div>

            </div>

            <ScrollCue target="page-content" />

        </section>

        <div id="page-content" class="mx-auto flex w-full max-w-6xl flex-col gap-20 px-6 py-16">

            <section
                v-for="(section, index) in SECTIONS"
                :key="section.title"
                class="flex flex-col gap-8 md:flex-row md:items-center md:gap-12"
                :class="{ 'md:flex-row-reverse': index % 2 === 1 }"
            >
                <!-- Below the fold, and one of these is a 936 KB photograph,
                     so none of them are fetched until they are scrolled to. -->
                <img
                    :src="section.image"
                    :alt="section.alt"
                    class="w-full border border-sub md:w-1/2"
                    loading="lazy"
                    decoding="async"
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

            <section>
                <h2 class="text-2xl font-semibold tracking-tight">How you play</h2>

                <p class="mt-4">
                    To play the game you only need to use three keys, so there is nothing to learn
                    before you start. The left and right arrows walk your
                    character across the platforms you drew, and the up arrow
                    jumps. That's it: no double jump to master, no combo to
                    remember and no menu to read first.
                </p>

                <div class="mt-8 flex flex-wrap items-center gap-x-12 gap-y-6">
                    <div class="flex items-center gap-3">
                        <span class="flex size-10 items-center justify-center border border-ink" aria-hidden="true">&larr;</span>
                        <span class="flex size-10 items-center justify-center border border-ink" aria-hidden="true">&rarr;</span>
                        <span>Left and right arrows to move</span>
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="flex size-10 items-center justify-center border border-ink" aria-hidden="true">&uarr;</span>
                        <span>Up arrow to jump</span>
                    </div>
                </div>

                <p class="mt-8">
                    Your job is to reach the goal without touching any hazards you drew. Touch a spike and you'll have to restart the level. There are no lives to
                    run out of and nothing to lose, so a level that beats you
                    the first ten times costs you nothing but another go.
                </p>

                <p class="mt-4">
                    Next to the game itself are two sliders, one for how fast
                    your character moves and one for how high it jumps. You can
                    drag them in the middle of a run and feel the difference
                    immediately, which is usually the quickest way to fix a
                    level that turned out slightly too hard or slightly too
                    easy. A gap that looks impossible often just needs a little
                    more jump. When you save a level, those two settings are
                    saved. So the next time you play it, you start out with exactly the same physics.
                </p>

                <p class="mt-4">
                    One last thing worth knowing: everything is drawn from your
                    picture, so the physics follow your lines rather than a
                    tidied-up version of them. A wobbly platform is genuinely
                    wobbly, a slope you drew by accident is a slope you can slide
                    down and a shape you thought was closed but is not will let
                    your character fall straight through it. That is not a bug,
                    it is your drawing and redrawing that one corner is usually
                    all it takes.
                </p>
            </section>

        </div>

    </AppLayout>
</template>
