<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomerPicker from '@/Components/CustomerPicker.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Icon from '@/Components/Icon.vue';
import Pagination from '@/Components/Pagination.vue';
import StatCard from '@/Components/StatCard.vue';
import TransactionItem from '@/Components/TransactionItem.vue';
import { formatDate, formatTime, isDeposit, naira, signedNaira, typeLabel } from '@/utils/format';

const props = defineProps({
    filters: Object,
    step: Object,
    today: String,
    months: Array,
    days: Array,
    summary: Object,
    entries: Object,
    customer: Object,
    collectors: Array,
    paymentMethods: Object,
});

const page = usePage();
const isAdmin = computed(() => page.props.auth.isAdmin);
const business = computed(() => page.props.business);
const errors = computed(() => page.props.errors);

const periods = [
    { value: 'day', label: 'Day' },
    { value: 'month', label: 'Month' },
    { value: 'range', label: 'Custom' },
];
const views = [
    { value: 'totals', label: 'Daily totals' },
    { value: 'list', label: 'Daily list' },
];
const types = [
    { value: '', label: 'All' },
    { value: 'credit', label: 'Deposits' },
    { value: 'debit', label: 'Withdrawals' },
];

const selectedCustomer = ref(props.customer);
const showFilters = ref(Boolean(props.customer || props.filters.collector || props.filters.method));

const periodLabel = computed(() => {
    const { period, date, from, to } = props.filters;
    if (period === 'day') return formatDate(date, { weekday: 'long' });
    if (period === 'month') return formatDate(from, { day: undefined, month: 'long' });
    return `${formatDate(from)} – ${formatDate(to)}`;
});

const filterSummary = computed(() =>
    [
        selectedCustomer.value && `${selectedCustomer.value.reg_no} ${selectedCustomer.value.full_name}`,
        props.filters.type && types.find((type) => type.value === props.filters.type).label,
        props.filters.collector && props.collectors.find((collector) => String(collector.id) === String(props.filters.collector))?.name,
        props.filters.method && props.paymentMethods[props.filters.method],
    ]
        .filter(Boolean)
        .join(' · '),
);

const averagePerDay = computed(() => (props.summary.activeDays ? props.summary.deposits / props.summary.activeDays : 0));
const dayTotals = computed(() => Object.fromEntries(props.days.map((day) => [day.date, day])));

// Entries arrive newest first; split them into one group per day.
const groups = computed(() => {
    const groups = [];
    for (const entry of props.entries?.data ?? []) {
        const date = entry.date.slice(0, 10);
        if (groups.at(-1)?.date !== date) groups.push({ date, entries: [] });
        groups.at(-1).entries.push(entry);
    }
    return groups;
});

function query(changes = {}) {
    const f = { ...props.filters, customer: selectedCustomer.value?.id ?? '', ...changes };
    const period = { day: { date: f.date }, month: { month: f.month }, range: { from: f.from, to: f.to } }[f.period];
    const params = { period: f.period, ...period, view: f.period === 'day' ? '' : f.view, customer: f.customer, type: f.type, collector: f.collector, method: f.method };
    return Object.fromEntries(Object.entries(params).filter(([, value]) => value !== '' && value != null));
}

function visit(changes) {
    router.get(route('collections.index'), query(changes), { preserveState: true, preserveScroll: true, replace: true });
}

function changePeriod(period) {
    if (period === props.filters.period) return;
    const lastDay = props.filters.to < props.today ? props.filters.to : props.today;
    visit({ period, view: 'totals', date: lastDay, to: lastDay });
}

function stepTo(value) {
    visit(props.filters.period === 'day' ? { date: value } : { month: value });
}

// Keep the range valid while either end moves.
function changeFrom(from) {
    if (from) visit({ from, to: from > props.filters.to ? from : props.filters.to });
}

function changeTo(to) {
    if (to) visit({ to, from: to < props.filters.from ? to : props.filters.from });
}

function dayLabel(date) {
    return formatDate(date, { weekday: 'short', year: props.filters.period === 'range' ? 'numeric' : undefined });
}

function entryCount(count = 0) {
    return `${count} ${count === 1 ? 'entry' : 'entries'}`;
}

function printPage() {
    window.print();
}

watch(selectedCustomer, () => visit());
</script>

<template>
    <AuthenticatedLayout title="Daily Collections" :subtitle="periodLabel">
        <template #actions>
            <button type="button" class="btn-secondary" @click="printPage">
                <Icon name="printer" class="h-5 w-5" /> Print
            </button>
        </template>

        <!-- Printed header -->
        <div class="mb-4 hidden print:block">
            <p class="text-xl font-bold">{{ business.business_name }}</p>
            <p class="font-semibold">Daily Collections: {{ periodLabel }}</p>
            <p v-if="filterSummary" class="text-sm">{{ filterSummary }}</p>
        </div>

        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center print:hidden">
            <div class="grid grid-cols-3 gap-1 rounded-xl bg-white p-1 ring-1 ring-slate-200 lg:w-72">
                <button
                    v-for="option in periods"
                    :key="option.value"
                    type="button"
                    :class="['rounded-lg py-2 text-sm font-semibold', filters.period === option.value ? 'bg-brand-800 text-white' : 'text-slate-600 hover:bg-slate-50']"
                    @click="changePeriod(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>

            <div v-if="filters.period === 'range'" class="flex flex-1 items-center gap-2 lg:justify-end">
                <input type="date" :value="filters.from" :max="today" aria-label="From" class="input min-w-0 flex-1 lg:w-44 lg:flex-none" @change="changeFrom($event.target.value)" />
                <span class="text-sm text-slate-400">to</span>
                <input type="date" :value="filters.to" :max="today" aria-label="To" class="input min-w-0 flex-1 lg:w-44 lg:flex-none" @change="changeTo($event.target.value)" />
            </div>
            <div v-else class="flex flex-1 items-center gap-2 lg:justify-end">
                <button type="button" class="btn-secondary px-3" :aria-label="`Previous ${filters.period}`" @click="stepTo(step.previous)">
                    <Icon name="chevron-left" class="h-5 w-5" />
                </button>
                <input
                    v-if="filters.period === 'day'"
                    type="date"
                    :value="filters.date"
                    :max="today"
                    aria-label="Day"
                    class="input min-w-0 flex-1 lg:w-44 lg:flex-none"
                    @change="$event.target.value && stepTo($event.target.value)"
                />
                <select v-else :value="filters.month" aria-label="Month" class="input min-w-0 flex-1 lg:w-48 lg:flex-none" @change="stepTo($event.target.value)">
                    <option v-for="month in months" :key="month.value" :value="month.value">{{ month.label }}</option>
                </select>
                <button type="button" class="btn-secondary px-3" :aria-label="`Next ${filters.period}`" :disabled="!step.next" @click="stepTo(step.next)">
                    <Icon name="chevron-right" class="h-5 w-5" />
                </button>
            </div>
        </div>
        <p v-if="errors.from || errors.to" class="field-error -mt-2 mb-4">{{ errors.from || errors.to }}</p>

        <div class="mb-5 space-y-3 print:hidden">
            <div class="flex items-center gap-2">
                <div class="flex flex-1 gap-1 overflow-x-auto">
                    <button
                        v-for="type in types"
                        :key="type.value"
                        type="button"
                        :class="[
                            'rounded-full px-4 py-1.5 text-sm font-medium',
                            filters.type === type.value ? 'bg-brand-800 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50',
                        ]"
                        @click="visit({ type: type.value })"
                    >
                        {{ type.label }}
                    </button>
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

            <div v-if="showFilters" class="card grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="text-xs font-medium text-slate-500 sm:col-span-2 lg:col-span-1">
                    Customer
                    <CustomerPicker v-model="selectedCustomer" class="mt-1" />
                </div>
                <label v-if="isAdmin" class="text-xs font-medium text-slate-500">
                    Staff
                    <select :value="filters.collector" class="input mt-1" @change="visit({ collector: $event.target.value })">
                        <option value="">Everyone</option>
                        <option v-for="collector in collectors" :key="collector.id" :value="collector.id">{{ collector.name }}</option>
                    </select>
                </label>
                <label class="text-xs font-medium text-slate-500">
                    Payment method
                    <select :value="filters.method" class="input mt-1" @change="visit({ method: $event.target.value })">
                        <option value="">Any method</option>
                        <option v-for="(label, value) in paymentMethods" :key="value" :value="value">{{ label }}</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-5">
            <StatCard label="Deposits" :value="naira(summary.deposits)" :hint="entryCount(summary.depositCount)" icon="arrow-down-circle" tone="green" />
            <StatCard label="Withdrawals" :value="naira(summary.withdrawals)" :hint="entryCount(summary.withdrawalCount)" icon="arrow-up-circle" tone="red" />
            <StatCard label="Net" :value="naira(summary.net)" hint="Deposits minus withdrawals" icon="banknotes" tone="brand" />
            <StatCard v-if="filters.period === 'day'" label="Customers" :value="summary.customers" hint="Served on this day" icon="users" tone="slate" />
            <StatCard
                v-else
                label="Collection Days"
                :value="`${summary.activeDays} of ${summary.totalDays}`"
                :hint="`Avg ${naira(averagePerDay)} deposited a day`"
                icon="calendar"
                tone="slate"
            />
        </div>

        <div v-if="filters.period !== 'day'" class="mt-5 flex gap-1 border-b border-slate-200 print:hidden">
            <button
                v-for="option in views"
                :key="option.value"
                type="button"
                :class="[
                    '-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold',
                    filters.view === option.value ? 'border-brand-700 text-brand-800' : 'border-transparent text-slate-500 hover:text-slate-700',
                ]"
                @click="visit({ view: option.value })"
            >
                {{ option.label }}
            </button>
        </div>

        <section v-if="filters.view === 'totals'" class="card mt-4 overflow-hidden print:shadow-none print:ring-0">
            <div class="flex flex-wrap items-baseline justify-between gap-2 px-4 pt-4 lg:px-6">
                <h2 class="font-semibold text-slate-900">Daily totals</h2>
                <p class="text-xs text-slate-500 print:hidden">Tap a date to see its entries.</p>
            </div>
            <!-- Phones: four money columns don't fit, so each day stacks its amounts. -->
            <div v-if="days.length" class="mt-3 divide-y divide-slate-100 border-t border-slate-100 sm:hidden print:hidden">
                <component
                    :is="day.count ? Link : 'div'"
                    v-for="day in days"
                    :key="day.date"
                    v-bind="day.count ? { href: route('collections.index', query({ period: 'day', date: day.date })) } : {}"
                    class="flex items-center justify-between gap-3 px-4 py-2.5"
                >
                    <div>
                        <p :class="['text-sm', day.count ? 'font-medium text-brand-700' : 'text-slate-400']">{{ dayLabel(day.date) }}</p>
                        <p v-if="day.count" class="text-xs text-slate-500">{{ entryCount(day.count) }}</p>
                    </div>
                    <div v-if="day.count" class="text-right text-sm">
                        <p v-if="day.deposits" class="font-semibold text-emerald-600">+{{ naira(day.deposits) }}</p>
                        <p v-if="day.withdrawals" class="font-semibold text-red-600">-{{ naira(day.withdrawals) }}</p>
                        <p v-if="day.deposits && day.withdrawals" class="text-xs text-slate-500">Net {{ naira(day.net) }}</p>
                    </div>
                    <span v-else class="text-slate-300">—</span>
                </component>
                <div class="flex items-start justify-between gap-3 bg-slate-50 px-4 py-3 text-sm font-semibold">
                    <div>
                        <p class="text-slate-900">Total</p>
                        <p class="text-xs font-normal text-slate-500">{{ entryCount(summary.count) }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-emerald-600">+{{ naira(summary.deposits) }}</p>
                        <p class="text-red-600">-{{ naira(summary.withdrawals) }}</p>
                        <p class="text-slate-900">Net {{ naira(summary.net) }}</p>
                    </div>
                </div>
            </div>

            <div v-if="days.length" class="mt-3 hidden overflow-x-auto sm:block print:block">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 lg:px-6">Date</th>
                            <th class="hidden px-4 py-2.5 text-right sm:table-cell">Entries</th>
                            <th class="px-4 py-2.5 text-right">Deposits</th>
                            <th class="px-4 py-2.5 text-right">Withdrawals</th>
                            <th class="px-4 py-2.5 text-right">Net</th>
                            <th class="hidden px-4 py-2.5 text-right md:table-cell lg:px-6">Running total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="day in days" :key="day.date" :class="day.count ? 'hover:bg-slate-50' : 'text-slate-400'">
                            <td class="whitespace-nowrap px-4 py-2.5 lg:px-6">
                                <Link v-if="day.count" :href="route('collections.index', query({ period: 'day', date: day.date }))" class="font-medium text-brand-700 hover:underline">
                                    {{ dayLabel(day.date) }}
                                </Link>
                                <span v-else>{{ dayLabel(day.date) }}</span>
                            </td>
                            <td class="hidden px-4 py-2.5 text-right sm:table-cell">{{ day.count || '—' }}</td>
                            <td :class="['whitespace-nowrap px-4 py-2.5 text-right', day.deposits && 'text-emerald-600']">{{ day.deposits ? naira(day.deposits) : '—' }}</td>
                            <td :class="['whitespace-nowrap px-4 py-2.5 text-right', day.withdrawals && 'text-red-600']">{{ day.withdrawals ? naira(day.withdrawals) : '—' }}</td>
                            <td :class="['whitespace-nowrap px-4 py-2.5 text-right', day.count && 'font-semibold text-slate-900']">{{ day.count ? naira(day.net) : '—' }}</td>
                            <td class="hidden whitespace-nowrap px-4 py-2.5 text-right text-slate-500 md:table-cell lg:px-6">{{ naira(day.running) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t-2 border-slate-200 bg-slate-50 font-semibold text-slate-900">
                        <tr>
                            <td class="px-4 py-3 lg:px-6">Total</td>
                            <td class="hidden px-4 py-3 text-right sm:table-cell">{{ summary.count }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-emerald-600">{{ naira(summary.deposits) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-red-600">{{ naira(summary.withdrawals) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">{{ naira(summary.net) }}</td>
                            <td class="hidden md:table-cell"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <EmptyState v-else icon="calendar" title="Nothing to show yet" text="This period hasn't started." />
        </section>

        <section v-else :class="['card overflow-hidden print:shadow-none print:ring-0', filters.period === 'day' ? 'mt-5' : 'mt-4']">
            <template v-if="groups.length">
                <!-- Phones -->
                <div v-for="group in groups" :key="group.date" class="md:hidden print:hidden">
                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-0.5 bg-slate-50 px-4 py-2.5">
                        <p class="text-sm font-semibold text-slate-900">{{ formatDate(group.date, { weekday: 'short' }) }}</p>
                        <p class="text-xs text-slate-500">
                            <span class="font-semibold text-emerald-600">+{{ naira(dayTotals[group.date]?.deposits) }}</span>
                            · <span class="font-semibold text-red-600">-{{ naira(dayTotals[group.date]?.withdrawals) }}</span>
                        </p>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <TransactionItem v-for="transaction in group.entries" :key="transaction.id" :transaction="transaction" />
                    </div>
                </div>

                <!-- Tablets and up, and print -->
                <table class="hidden min-w-full text-sm md:table print:table">
                    <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Time</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Method</th>
                            <th class="px-4 py-3">Collected by</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-6 py-3 print:hidden"><span class="sr-only">Receipt</span></th>
                        </tr>
                    </thead>
                    <tbody v-for="group in groups" :key="group.date" class="divide-y divide-slate-100 border-t border-slate-100">
                        <tr class="bg-slate-50">
                            <th colspan="7" class="px-6 py-2.5 text-left font-normal">
                                <div class="flex flex-wrap items-center justify-between gap-x-4">
                                    <span class="font-semibold text-slate-900">{{ formatDate(group.date, { weekday: 'long' }) }}</span>
                                    <span class="text-xs text-slate-500">
                                        {{ entryCount(dayTotals[group.date]?.count) }}
                                        · Deposits <span class="font-semibold text-emerald-600">{{ naira(dayTotals[group.date]?.deposits) }}</span>
                                        · Withdrawals <span class="font-semibold text-red-600">{{ naira(dayTotals[group.date]?.withdrawals) }}</span>
                                        · Net <span class="font-semibold text-slate-900">{{ naira(dayTotals[group.date]?.net) }}</span>
                                    </span>
                                </div>
                            </th>
                        </tr>
                        <tr v-for="t in group.entries" :key="t.id" class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-6 py-3 text-slate-500">{{ formatTime(t.created_at) }}</td>
                            <td class="px-4 py-3">
                                <Link :href="route('customers.show', t.customer_id)" class="font-medium text-slate-900 hover:text-brand-700">{{ t.customer?.full_name }}</Link>
                                <span class="block text-xs text-slate-500">{{ t.customer?.reg_no }}</span>
                            </td>
                            <td :class="['px-4 py-3 font-medium', isDeposit(t) ? 'text-emerald-600' : 'text-red-600']">{{ typeLabel(t.type) }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ paymentMethods[t.payment_method] || t.payment_method }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ t.collector?.name || '—' }}</td>
                            <td :class="['whitespace-nowrap px-4 py-3 text-right font-semibold', isDeposit(t) ? 'text-emerald-600' : 'text-red-600']">{{ signedNaira(t) }}</td>
                            <td class="px-6 py-3 text-right print:hidden">
                                <Link :href="route('transactions.show', t.id)" class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:text-brand-900">
                                    <Icon name="document-text" class="h-4 w-4" /> Receipt
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </template>
            <EmptyState v-else icon="arrows-right-left" title="No collections found" text="Try another date or change the filters." />
        </section>

        <div v-if="entries" class="mt-4 print:hidden">
            <Pagination :paginator="entries" />
        </div>
    </AuthenticatedLayout>
</template>
