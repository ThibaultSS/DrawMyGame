<script setup>
const props = defineProps({
    target: { type: String, required: true },

    onLight: { type: Boolean, default: false }
});

const ARROW = {
    image: null,
    alt: ""
};

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
