<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Icon from '@/Components/Icon.vue';
import Pagination from '@/Components/Pagination.vue';
import TransactionItem from '@/Components/TransactionItem.vue';
import { formatShortDate, formatTime, isDeposit, naira, signedNaira, typeLabel } from '@/utils/format';

const props = defineProps({
    transactions: Object,
    totals: Object,
    filters: Object,
    collectors: Array,
    paymentMethods: Object,
});

const isAdmin = computed(() => usePage().props.auth.isAdmin);
const filters = reactive({
    search: props.filters.search || '',
    type: props.filters.type || '',
    from: props.filters.from || '',
    to: props.filters.to || '',
    collector: props.filters.collector || '',
});
const showFilters = ref(Boolean(props.filters.from || props.filters.to || props.filters.collector));

const title = computed(() => ({ credit: 'Deposits', debit: 'Withdrawals' })[props.filters.type] || 'Transactions');
const types = [
    { value: '', label: 'All' },
    { value: 'credit', label: 'Deposits' },
    { value: 'debit', label: 'Withdrawals' },
];

function apply() {
    const params = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));
    router.get(route('transactions.index'), params, { preserveState: true, preserveScroll: true, replace: true });
}

let timer = null;
watch(() => filters.search, () => {
    clearTimeout(timer);
    timer = setTimeout(apply, 300);
});
watch(() => [filters.type, filters.from, filters.to, filters.collector], apply);
</script>

<template>
    <AuthenticatedLayout :title="title">
        <template #actions>
            <Link :href="route('transactions.create', { type: filters.type === 'debit' ? 'debit' : 'credit' })" class="btn-primary hidden lg:inline-flex">
                <Icon name="plus" class="h-4 w-4" /> {{ filters.type === 'debit' ? 'New Withdrawal' : 'New Deposit' }}
            </Link>
        </template>

        <div class="mb-4 space-y-3">
            <div class="flex gap-2">
                <div class="relative flex-1 lg:max-w-md">
                    <Icon name="magnifying-glass" class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                    <input v-model="filters.search" type="search" class="input pl-11" placeholder="Search customer name, phone or ID" />
                </div>
                <button
                    type="button"
                    :class="['btn px-3 ring-1 ring-inset', showFilters ? 'bg-brand-50 text-brand-800 ring-brand-200' : 'bg-white text-slate-600 ring-slate-300']"
                    aria-label="Filters"
                    @click="showFilters = !showFilters"
                >
                    <Icon name="funnel" class="h-5 w-5" />
                    <span class="hidden sm:inline">Filters</span>
                </button>
            </div>

            <div class="flex gap-1 overflow-x-auto">
                <button
                    v-for="type in types"
                    :key="type.value"
                    type="button"
                    :class="[
                        'rounded-full px-4 py-1.5 text-sm font-medium',
                        filters.type === type.value ? 'bg-brand-800 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50',
                    ]"
                    @click="filters.type = type.value"
                >
                    {{ type.label }}
                </button>
            </div>

            <div v-if="showFilters" class="card grid gap-3 p-4 sm:grid-cols-3">
                <label class="text-xs font-medium text-slate-500">
                    From
                    <input v-model="filters.from" type="date" class="input mt-1" />
                </label>
                <label class="text-xs font-medium text-slate-500">
                    To
                    <input v-model="filters.to" type="date" class="input mt-1" />
                </label>
                <label v-if="isAdmin" class="text-xs font-medium text-slate-500">
                    Staff
                    <select v-model="filters.collector" class="input mt-1">
                        <option value="">Everyone</option>
                        <option v-for="collector in collectors" :key="collector.id" :value="collector.id">{{ collector.name }}</option>
                    </select>
                </label>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="card px-4 py-3">
                    <p class="text-xs text-slate-500">Deposits</p>
                    <p class="font-bold text-emerald-600">{{ naira(totals.deposits) }}</p>
                </div>
                <div class="card px-4 py-3">
                    <p class="text-xs text-slate-500">Withdrawals</p>
                    <p class="font-bold text-red-600">{{ naira(totals.withdrawals) }}</p>
                </div>
            </div>
        </div>

        <div class="card overflow-hidden">
            <template v-if="transactions.data.length">
                <div class="divide-y divide-slate-100 md:hidden">
                    <TransactionItem v-for="transaction in transactions.data" :key="transaction.id" :transaction="transaction" />
                </div>

                <table class="hidden min-w-full text-sm md:table">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Date</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Method</th>
                            <th class="px-4 py-3">Collected by</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-6 py-3"><span class="sr-only">Receipt</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="t in transactions.data" :key="t.id" class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-6 py-3 text-slate-700">
                                {{ formatShortDate(t.date) }}
                                <span class="block text-xs text-slate-400">{{ formatTime(t.created_at) }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <Link :href="route('customers.show', t.customer_id)" class="font-medium text-slate-900 hover:text-brand-700">{{ t.customer?.full_name }}</Link>
                                <span class="block text-xs text-slate-500">{{ t.customer?.reg_no }}</span>
                            </td>
                            <td :class="['px-4 py-3 font-medium', isDeposit(t) ? 'text-emerald-600' : 'text-red-600']">{{ typeLabel(t.type) }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ paymentMethods[t.payment_method] || t.payment_method }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ t.collector?.name || '—' }}</td>
                            <td :class="['whitespace-nowrap px-4 py-3 text-right font-semibold', isDeposit(t) ? 'text-emerald-600' : 'text-red-600']">
                                {{ signedNaira(t) }}
                            </td>
                            <td class="px-6 py-3 text-right">
                                <Link :href="route('transactions.show', t.id)" class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:text-brand-900">
                                    <Icon name="document-text" class="h-4 w-4" /> Receipt
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </template>
            <EmptyState v-else icon="arrows-right-left" title="No transactions found" text="Try changing the search or filters." />
        </div>

        <div class="mt-4">
            <Pagination :paginator="transactions" />
        </div>
    </AuthenticatedLayout>
</template>
