<script setup>
defineProps({
    message: {
        type: String,
        required: true
    }
});

defineEmits(["close", "retry"]);
</script>

<template>
    <div class="fixed inset-0 z-40 flex items-center justify-center bg-ink/50">
        <div class="flex flex-col items-center gap-6 border border-sub bg-page px-12 py-10 text-center">

            <h2 class="text-3xl font-semibold tracking-tight">{{ message }}</h2>

            <div class="flex gap-4">
                <button type="button" class="border border-sub px-4 py-2" @click="$emit('close')">
                    Close
                </button>

                <button type="button" class="bg-ink px-4 py-2 text-page" @click="$emit('retry')">
                    Retry
                </button>
            </div>

        </div>
    </div>
</template>
