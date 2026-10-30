<script setup>
import { initials } from '@/utils/format';

defineProps({
    name: { type: String, default: '' },
    size: { type: String, default: 'h-10 w-10 text-sm' },
});
</script>

<template>
    <span :class="['inline-flex shrink-0 items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-800', size]">
        {{ initials(name) || '?' }}
    </span>
</template>
