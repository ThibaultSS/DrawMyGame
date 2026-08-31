<script setup>
defineProps({
    colour: {
        type: String,
        default: null
    },
    size: {
        type: String,
        default: "size-3"
    }
});
</script>

<template>
    <span
        class="border border-sub"
        :class="size"
        :style="colour ? { backgroundColor: colour } : null"
    ></span>
</template>
