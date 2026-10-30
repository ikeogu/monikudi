<script setup>
import Icon from '@/Components/Icon.vue';

defineProps({
    label: String,
    value: [String, Number],
    hint: String,
    icon: String,
    tone: { type: String, default: 'brand' },
});

const tones = {
    brand: 'bg-brand-50 text-brand-700',
    green: 'bg-emerald-50 text-emerald-600',
    red: 'bg-red-50 text-red-600',
    amber: 'bg-amber-50 text-amber-600',
    slate: 'bg-slate-100 text-slate-600',
};
</script>

<template>
    <!-- Stacked on phones so full naira amounts fit in a two-column grid; side by side from lg up. -->
    <div class="card flex min-w-0 flex-col gap-2 p-4 lg:flex-row lg:items-start lg:gap-3 lg:p-5">
        <div class="flex items-center gap-2.5 lg:contents">
            <span :class="['flex h-9 w-9 shrink-0 items-center justify-center rounded-full lg:h-12 lg:w-12', tones[tone]]">
                <Icon :name="icon" class="h-5 w-5 lg:h-6 lg:w-6" />
            </span>
            <p class="text-xs font-medium leading-tight text-slate-500 lg:hidden">{{ label }}</p>
        </div>
        <div class="min-w-0">
            <p class="hidden text-sm font-medium text-slate-500 lg:block">{{ label }}</p>
            <p class="break-words text-[15px] font-bold leading-snug tracking-tight text-slate-900 lg:mt-0.5 lg:text-xl 2xl:text-2xl">{{ value }}</p>
            <p v-if="hint" class="mt-0.5 text-xs leading-tight text-slate-400">{{ hint }}</p>
        </div>
    </div>
</template>
