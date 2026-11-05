<script setup>
import { Link } from '@inertiajs/vue3';
import Avatar from '@/Components/Avatar.vue';
import { entryTone, formatShortDate, formatTime, signedNaira, typeLabel } from '@/utils/format';

defineProps({
    transaction: { type: Object, required: true },
    showCollector: { type: Boolean, default: true },
});
</script>

<template>
    <Link :href="route('transactions.show', transaction.id)" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
        <Avatar :name="transaction.customer?.full_name" />
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-slate-900">{{ transaction.customer?.reg_no }}</p>
            <p class="truncate text-sm text-slate-600">{{ transaction.customer?.full_name }}</p>
            <p v-if="transaction.plan || (showCollector && transaction.collector)" class="truncate text-xs text-slate-400">
                {{ [transaction.plan?.name, showCollector && transaction.collector?.name].filter(Boolean).join(' · ') }}
            </p>
        </div>
        <div class="shrink-0 text-right">
            <p :class="['text-sm font-semibold', entryTone(transaction)]">
                {{ signedNaira(transaction) }}
            </p>
            <p class="text-xs text-slate-500">{{ typeLabel(transaction.type) }}</p>
            <p class="text-xs text-slate-400">{{ formatShortDate(transaction.date) }} · {{ formatTime(transaction.created_at) }}</p>
        </div>
    </Link>
</template>
