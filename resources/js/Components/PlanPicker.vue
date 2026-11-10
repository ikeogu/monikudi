<script setup>
import { monthLabel, naira } from '@/utils/format';

defineProps({
    modelValue: { type: String, default: '' },
    plans: { type: Array, required: true },
    // The entry date, so the page status speaks about the right month.
    date: { type: String, required: true },
});
defineEmits(['update:modelValue']);

function pageStatus(plan, date) {
    if (plan.status === 'completed') return 'Target reached · pay out to close';
    if (!plan.daily_amount) return 'No daily amount set yet, so no commission';
    const page = plan.current_page;
    if (!page || page.month !== date.slice(0, 7)) return `Next payment starts ${monthLabel(date.slice(0, 7))}'s page`;
    return `${monthLabel(page.month)} page · Day ${page.day} of ${page.days}`;
}
</script>

<template>
    <div class="grid gap-2 sm:grid-cols-2" role="radiogroup" aria-label="Savings plan">
        <button
            v-for="plan in plans"
            :key="plan.id"
            type="button"
            role="radio"
            :aria-checked="modelValue === plan.id"
            :class="[
                'rounded-xl p-3.5 text-left ring-1 transition',
                modelValue === plan.id ? 'bg-brand-50 ring-2 ring-brand-600' : 'ring-slate-200 hover:bg-slate-50',
            ]"
            @click="$emit('update:modelValue', plan.id)"
        >
            <span class="flex items-baseline justify-between gap-2">
                <span class="font-semibold text-slate-900">{{ plan.name }}</span>
                <span class="text-sm font-semibold text-emerald-600">{{ naira(plan.balance) }}</span>
            </span>
            <span class="mt-0.5 block text-xs text-slate-500">
                <template v-if="plan.daily_amount">{{ naira(plan.daily_amount) }} a day · </template>{{ pageStatus(plan, date) }}
            </span>
        </button>
    </div>
</template>
