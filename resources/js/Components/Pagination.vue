<script setup>
/**
 * Page links for a Laravel paginator. The controller passes the paginator
 * straight through as a prop, and this component renders its links array:
 * previous, numbered pages, next — with the current page filled in and
 * unreachable ends (previous on page one, next on the last) greyed out.
 */
import { Link } from "@inertiajs/vue3";

defineProps({
    links: {
        type: Array,
        required: true
    }
});
</script>

<template>
    <!-- Three links is previous + a single page + next: nothing to paginate. -->
    <nav v-if="links.length > 3" class="flex flex-wrap justify-center gap-2 text-sm">
        <template v-for="(link, index) in links" :key="index">

            <!--
                v-html because Laravel writes the previous/next labels with
                HTML entities (&laquo;/&raquo;). The labels come from the
                framework, not from user input.
            -->
            <Link
                v-if="link.url"
                :href="link.url"
                class="border px-3 py-1.5"
                :class="link.active ? 'border-ink bg-ink text-page' : 'border-sub hover:border-ink'"
                v-html="link.label"
            />

            <span
                v-else
                class="border border-sub px-3 py-1.5 opacity-50"
                v-html="link.label"
            />

        </template>
    </nav>
</template>
