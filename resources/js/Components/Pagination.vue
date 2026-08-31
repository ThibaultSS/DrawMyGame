<script setup>
import { Link } from "@inertiajs/vue3";

defineProps({
    links: {
        type: Array,
        required: true
    }
});
</script>

<template>
    <nav v-if="links.length > 3" class="flex flex-wrap justify-center gap-2 text-sm">
        <template v-for="(link, index) in links" :key="index">

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
