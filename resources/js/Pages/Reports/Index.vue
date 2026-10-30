<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DepositsChart from '@/Components/DepositsChart.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Icon from '@/Components/Icon.vue';
import StatCard from '@/Components/StatCard.vue';
import TransactionItem from '@/Components/TransactionItem.vue';
import { formatDate, formatShortDate, naira } from '@/utils/format';

const props = defineProps({
    period: String,
    range: Object,
    summary: Object,
    byDay: Array,
    byCollector: Array,
    byMethod: Array,
    transactions: Array,
});

const periods = [
    { value: 'daily', label: 'Daily' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'monthly', label: 'Monthly' },
];

const rangeLabel = computed(() => {
    if (props.period === 'daily') return formatDate(props.range.from, { weekday: 'long' });
    if (props.period === 'monthly') return formatDate(props.range.from, { day: undefined, month: 'long' });
    return `${formatDate(props.range.from)} – ${formatDate(props.range.to)}`;
});

const activeDays = computed(() => props.byDay.filter((day) => day.count > 0));

function visit(params) {
    router.get(route('reports.index'), { period: props.period, date: props.range.from, ...params }, { preserveScroll: true });
}

function printReport() {
    window.print();
}
</script>

<template>
    <AuthenticatedLayout title="Reports" :subtitle="rangeLabel">
        <template #actions>
            <a :href="route('reports.export', { period, date: range.from })" class="btn-secondary">
                <Icon name="arrow-down-tray" class="h-5 w-5" /> Export CSV
            </a>
            <button type="button" class="btn-secondary" @click="printReport">
                <Icon name="printer" class="h-5 w-5" /> Print
            </button>
        </template>

        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center print:hidden">
            <div class="grid grid-cols-3 gap-1 rounded-xl bg-white p-1 ring-1 ring-slate-200 sm:w-80">
                <button
                    v-for="option in periods"
                    :key="option.value"
                    type="button"
                    :class="['rounded-lg py-2 text-sm font-semibold', period === option.value ? 'bg-brand-800 text-white' : 'text-slate-600 hover:bg-slate-50']"
                    @click="visit({ period: option.value })"
                >
                    {{ option.label }}
                </button>
            </div>

            <div class="flex flex-1 items-center gap-2 sm:justify-end">
                <button type="button" class="btn-secondary px-3" aria-label="Previous period" @click="visit({ date: range.previous })">
                    <Icon name="chevron-left" class="h-5 w-5" />
                </button>
                <input type="date" :value="range.from" class="input flex-1 sm:w-44 sm:flex-none" @change="visit({ date: $event.target.value })" />
                <button type="button" class="btn-secondary px-3" aria-label="Next period" :disabled="!range.next" @click="visit({ date: range.next })">
                    <Icon name="chevron-right" class="h-5 w-5" />
                </button>
            </div>
        </div>

        <p class="mb-3 text-lg font-semibold text-slate-900 lg:hidden print:block">{{ rangeLabel }}</p>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-5">
            <StatCard label="Deposits" :value="naira(summary.deposits)" :hint="`${summary.depositCount} entries`" icon="arrow-down-circle" tone="green" />
            <StatCard label="Withdrawals" :value="naira(summary.withdrawals)" :hint="`${summary.withdrawalCount} entries`" icon="arrow-up-circle" tone="red" />
            <StatCard label="Net Savings" :value="naira(summary.net)" hint="Deposits minus withdrawals" icon="banknotes" tone="brand" />
            <StatCard label="Customers Served" :value="summary.customersServed" :hint="`${summary.newCustomers} newly registered`" icon="users" tone="slate" />
        </div>

        <section v-if="period !== 'daily'" class="card mt-5 p-4 lg:p-6 print:break-inside-avoid">
            <h2 class="font-semibold text-slate-900">Daily breakdown</h2>
            <div class="mt-4 print:hidden">
                <DepositsChart :days="byDay" :height="220" />
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5">Date</th>
                            <th class="hidden px-4 py-2.5 text-right sm:table-cell">Entries</th>
                            <th class="px-4 py-2.5 text-right">Deposits</th>
                            <th class="px-4 py-2.5 text-right">Withdrawals</th>
                            <th class="px-4 py-2.5 text-right">Net</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="day in activeDays" :key="day.date" class="cursor-pointer hover:bg-slate-50" @click="visit({ period: 'daily', date: day.date })">
                            <td class="px-4 py-2.5 font-medium text-brand-700">{{ formatShortDate(day.date) }}</td>
                            <td class="hidden px-4 py-2.5 text-right text-slate-600 sm:table-cell">{{ day.count }}</td>
                            <td class="px-4 py-2.5 text-right text-emerald-600">{{ naira(day.deposits) }}</td>
                            <td class="px-4 py-2.5 text-right text-red-600">{{ naira(day.withdrawals) }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold text-slate-900">{{ naira(day.net) }}</td>
                        </tr>
                        <tr v-if="!activeDays.length">
                            <td colspan="5" class="px-4 py-6 text-center text-slate-500">No entries in this period.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            <section class="card overflow-hidden print:break-inside-avoid">
                <h2 class="px-4 pt-4 font-semibold text-slate-900 lg:px-6">By staff</h2>
                <div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 lg:px-6">Staff</th>
                            <th class="hidden px-4 py-2.5 text-right sm:table-cell">Entries</th>
                            <th class="px-4 py-2.5 text-right">Deposits</th>
                            <th class="px-4 py-2.5 text-right lg:px-6">Withdrawals</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in byCollector" :key="row.name">
                            <td class="px-4 py-2.5 font-medium text-slate-900 lg:px-6">{{ row.name }}</td>
                            <td class="hidden px-4 py-2.5 text-right text-slate-600 sm:table-cell">{{ row.count }}</td>
                            <td class="px-4 py-2.5 text-right text-emerald-600">{{ naira(row.deposits) }}</td>
                            <td class="px-4 py-2.5 text-right text-red-600 lg:px-6">{{ naira(row.withdrawals) }}</td>
                        </tr>
                        <tr v-if="!byCollector.length">
                            <td colspan="4" class="px-4 py-6 text-center text-slate-500">No entries in this period.</td>
                        </tr>
                    </tbody>
                </table></div>
            </section>

            <section class="card overflow-hidden print:break-inside-avoid">
                <h2 class="px-4 pt-4 font-semibold text-slate-900 lg:px-6">By payment method</h2>
                <div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 lg:px-6">Method</th>
                            <th class="hidden px-4 py-2.5 text-right sm:table-cell">Entries</th>
                            <th class="px-4 py-2.5 text-right">Deposits</th>
                            <th class="px-4 py-2.5 text-right lg:px-6">Withdrawals</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in byMethod" :key="row.method">
                            <td class="px-4 py-2.5 font-medium text-slate-900 lg:px-6">{{ row.method }}</td>
                            <td class="hidden px-4 py-2.5 text-right text-slate-600 sm:table-cell">{{ row.count }}</td>
                            <td class="px-4 py-2.5 text-right text-emerald-600">{{ naira(row.deposits) }}</td>
                            <td class="px-4 py-2.5 text-right text-red-600 lg:px-6">{{ naira(row.withdrawals) }}</td>
                        </tr>
                        <tr v-if="!byMethod.length">
                            <td colspan="4" class="px-4 py-6 text-center text-slate-500">No entries in this period.</td>
                        </tr>
                    </tbody>
                </table></div>
            </section>
        </div>

        <section v-if="period === 'daily'" class="card mt-5 overflow-hidden">
            <h2 class="px-4 pt-4 font-semibold text-slate-900 lg:px-6">Entries</h2>
            <div v-if="transactions.length" class="mt-2 divide-y divide-slate-100">
                <TransactionItem v-for="transaction in transactions" :key="transaction.id" :transaction="transaction" />
            </div>
            <EmptyState v-else icon="calendar" title="No entries on this day">
                <Link :href="route('transactions.create')" class="btn-primary"><Icon name="plus" class="h-4 w-4" /> New Deposit</Link>
            </EmptyState>
        </section>
    </AuthenticatedLayout>
</template>
