<script setup>
/**
 * The bouncing arrow at the foot of a full-height banner.
 *
 * The banners fill the screen on purpose, which means nothing below them is
 * visible and a visitor has no way of knowing the page continues. This is that
 * signal: it nudges up and down, and clicking it scrolls to the first block of
 * text. Scrolling yourself works exactly as it always did — this only makes the
 * page's own length obvious.
 */
const props = defineProps({
    // The id of the element to scroll to when the arrow is clicked.
    target: { type: String, required: true },

    // A white banner needs a black arrow. The svg draws in currentColor, so
    // this one class decides the whole thing.
    onLight: { type: Boolean, default: false }
});

/**
 * Artwork may replace the drawn chevron later: point `image` at a file under
 * /assets and the <img> takes over from the inline arrow. Both banners use this
 * component, so that is one change for the whole site.
 */
const ARROW = {
    image: null,
    alt: ""
};

// Honoured twice over: the bounce is a motion-safe: variant in the markup, and
// the scroll itself jumps rather than glides.
const reduceMotion =
    typeof window !== "undefined" &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;

function scrollToContent() {
    document.getElementById(props.target)?.scrollIntoView({
        behavior: reduceMotion ? "auto" : "smooth",
        block: "start"
    });
}
</script>

<template>
    <!--
        inset-x-0 with mx-auto centres it against the banner rather than against
        the copy, so it stays put whatever the heading wraps to.
    -->
    <button
        type="button"
        class="absolute inset-x-0 bottom-8 mx-auto flex size-12 items-center justify-center motion-safe:animate-bounce"
        :class="onLight ? 'text-ink' : 'text-page'"
        aria-label="Scroll to the page content"
        @click="scrollToContent"
    >
        <img
            v-if="ARROW.image"
            :src="ARROW.image"
            :alt="ARROW.alt"
            class="size-8 object-contain"
        >

        <!-- currentColor, so the arrow is the same text-page white as the copy
             above it and no new colour enters the page. -->
        <svg
            v-else
            class="size-8"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
        >
            <path d="M6 9l6 6 6-6" />
        </svg>
    </button>
</template>
