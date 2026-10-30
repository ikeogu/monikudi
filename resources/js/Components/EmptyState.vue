<script setup>
import Icon from '@/Components/Icon.vue';

defineProps({
    icon: { type: String, default: 'document-text' },
    title: String,
    text: String,
});
</script>

<template>
    <div class="flex flex-col items-center px-6 py-12 text-center">
        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
            <Icon :name="icon" class="h-6 w-6" />
        </span>
        <p class="mt-3 font-semibold text-slate-800">{{ title }}</p>
        <p v-if="text" class="mt-1 max-w-sm text-sm text-slate-500">{{ text }}</p>
        <div v-if="$slots.default" class="mt-4"><slot /></div>
    </div>
</template>
