<script setup>
import { computed, reactive } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Icon from '@/Components/Icon.vue';
import { formatDate, formatShortDate, formatTime, isDeposit, naira, signedNaira, typeLabel } from '@/utils/format';
import { statementText, whatsappUrl } from '@/utils/share';

const props = defineProps({
    customer: Object,
    summary: Object,
    transactions: Array,
    filters: Object,
});

const page = usePage();
const isAdmin = computed(() => page.props.auth.isAdmin);
const business = computed(() => page.props.business);

const range = reactive({ from: props.filters.from || '', to: props.filters.to || '' });
const filtered = computed(() => Boolean(props.filters.from || props.filters.to));
// Older versions of the app could leave the stored balance out of step with the entries.
const outOfSync = computed(() => Math.abs(props.summary.historyBalance - props.summary.balance) >= 0.01);

const shareUrl = computed(() =>
    whatsappUrl(props.customer.phone, statementText(props.customer, props.summary, props.transactions, business.value)),
);

function applyRange() {
    router.get(route('customers.show', props.customer.id), { from: range.from || undefined, to: range.to || undefined }, { preserveScroll: true });
}

function clearRange() {
    router.get(route('customers.show', props.customer.id));
}

function printStatement() {
    window.print();
}

function recalculate() {
    if (confirm('Recalculate this balance from the transaction history?')) {
        router.post(route('customers.recalculate', props.customer.id), {}, { preserveScroll: true });
    }
}
</script>

<template>
    <AuthenticatedLayout title="Customer Statement" :back="route('customers.index')">
        <template #actions>
            <Link :href="route('transactions.create', { type: 'credit', customer: customer.id })" class="btn-primary flex-1 sm:flex-none">
                <Icon name="arrow-down-circle" class="h-5 w-5" /> Deposit
            </Link>
            <Link :href="route('transactions.create', { type: 'debit', customer: customer.id })" class="btn-secondary flex-1 sm:flex-none">
                <Icon name="arrow-up-circle" class="h-5 w-5" /> Withdraw
            </Link>
        </template>

        <!-- Printed header -->
        <div class="mb-6 hidden border-b border-slate-300 pb-4 print:block">
            <p class="text-xl font-bold">{{ business.business_name }}</p>
            <p class="text-sm">{{ business.address }} {{ business.phone }}</p>
            <p class="mt-2 font-semibold">Customer Statement{{ filtered ? `: ${formatDate(filters.from) || 'start'} – ${formatDate(filters.to) || 'today'}` : '' }}</p>
        </div>

        <div class="card p-4 lg:p-6 print:shadow-none print:ring-0">
            <div class="flex items-start gap-4">
                <Avatar :name="customer.full_name" size="h-14 w-14 text-lg lg:h-16 lg:w-16" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-brand-700">{{ customer.reg_no }}</p>
                    <p class="truncate text-lg font-bold text-slate-900">{{ customer.full_name }}</p>
                    <p class="text-sm text-slate-500">{{ customer.phone || 'No phone number' }}</p>
                    <p v-if="customer.address" class="text-sm text-slate-500">{{ customer.address }}</p>
                </div>
                <div class="flex gap-1 print:hidden">
                    <a :href="shareUrl" target="_blank" rel="noopener" class="rounded-lg p-2 text-[#1ebe5b] hover:bg-emerald-50" title="Share on WhatsApp">
                        <Icon name="whatsapp" class="h-5 w-5" />
                    </a>
                    <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="Print statement" @click="printStatement">
                        <Icon name="printer" class="h-5 w-5" />
                    </button>
                    <Link :href="route('customers.edit', customer.id)" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="Edit customer">
                        <Icon name="pencil" class="h-5 w-5" />
                    </Link>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-3 gap-2 lg:gap-4">
                <div class="rounded-xl bg-slate-50 p-3 lg:p-4">
                    <p class="text-xs text-slate-500">Total Deposits</p>
                    <p class="mt-1 text-sm font-bold text-slate-900 lg:text-xl">{{ naira(summary.deposits) }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 lg:p-4">
                    <p class="text-xs text-slate-500">Total Withdrawals</p>
                    <p class="mt-1 text-sm font-bold text-slate-900 lg:text-xl">{{ naira(summary.withdrawals) }}</p>
                </div>
                <div class="rounded-xl bg-emerald-50 p-3 lg:p-4">
                    <p class="text-xs text-emerald-700">Current Balance</p>
                    <p class="mt-1 text-sm font-bold text-emerald-700 lg:text-xl">{{ naira(summary.balance) }}</p>
                </div>
            </div>

            <div v-if="isAdmin && outOfSync" class="mt-4 flex flex-col gap-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900 sm:flex-row sm:items-center print:hidden">
                <Icon name="exclamation-triangle" class="h-5 w-5 shrink-0" />
                <p class="flex-1">
                    The saved balance ({{ naira(summary.balance) }}) doesn't match this customer's history ({{ naira(summary.historyBalance) }}).
                    This can happen with entries edited in the old version of the app.
                </p>
                <button type="button" class="btn-secondary py-1.5" @click="recalculate">
                    <Icon name="arrow-path" class="h-4 w-4" /> Recalculate
                </button>
            </div>
        </div>

        <div class="card mt-5 overflow-hidden print:mt-4 print:shadow-none print:ring-0">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-4 lg:flex-row lg:items-end lg:px-6">
                <h2 class="flex-1 font-semibold text-slate-900">Transaction History</h2>
                <form class="flex flex-wrap items-end gap-2 print:hidden" @submit.prevent="applyRange">
                    <label class="text-xs text-slate-500">
                        From
                        <input v-model="range.from" type="date" class="input mt-1 py-2" />
                    </label>
                    <label class="text-xs text-slate-500">
                        To
                        <input v-model="range.to" type="date" class="input mt-1 py-2" />
                    </label>
                    <button type="submit" class="btn-secondary py-2">Apply</button>
                    <button v-if="filtered" type="button" class="btn py-2 text-slate-500" @click="clearRange">Clear</button>
                </form>
            </div>

            <div v-if="filtered" class="flex justify-between bg-slate-50 px-4 py-2 text-sm text-slate-600 lg:px-6">
                <span>Opening balance</span>
                <span class="font-semibold">{{ naira(summary.opening) }}</span>
            </div>

            <template v-if="transactions.length">
                <div class="divide-y divide-slate-100 md:hidden print:hidden">
                    <Link
                        v-for="t in transactions"
                        :key="t.id"
                        :href="route('transactions.show', t.id)"
                        class="flex items-start justify-between gap-3 px-4 py-3 hover:bg-slate-50"
                    >
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ formatShortDate(t.date) }}</p>
                            <p :class="['text-sm', isDeposit(t) ? 'text-emerald-600' : 'text-red-600']">{{ typeLabel(t.type) }}</p>
                            <p v-if="t.collector" class="text-xs text-slate-400">{{ t.collector.name }}</p>
                        </div>
                        <div class="text-right">
                            <p :class="['text-sm font-semibold', isDeposit(t) ? 'text-emerald-600' : 'text-red-600']">{{ signedNaira(t) }}</p>
                            <p class="text-xs text-slate-500">Balance: {{ naira(t.running_balance) }}</p>
                        </div>
                    </Link>
                </div>

                <table class="hidden min-w-full text-sm md:table print:table">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Date</th>
                            <th class="px-4 py-3">Receipt</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Collected by</th>
                            <th class="px-4 py-3">Remarks</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-6 py-3 text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="t in transactions" :key="t.id" class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-6 py-3 text-slate-700">
                                {{ formatShortDate(t.date) }}
                                <span class="block text-xs text-slate-400">{{ formatTime(t.created_at) }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <Link :href="route('transactions.show', t.id)" class="font-medium text-brand-700 hover:underline">{{ t.receipt_no }}</Link>
                            </td>
                            <td :class="['px-4 py-3 font-medium', isDeposit(t) ? 'text-emerald-600' : 'text-red-600']">{{ typeLabel(t.type) }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ t.collector?.name || '—' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ t.description || '—' }}</td>
                            <td :class="['whitespace-nowrap px-4 py-3 text-right font-semibold', isDeposit(t) ? 'text-emerald-600' : 'text-red-600']">
                                {{ signedNaira(t) }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-3 text-right font-medium text-slate-900">{{ naira(t.running_balance) }}</td>
                        </tr>
                    </tbody>
                </table>
            </template>

            <EmptyState
                v-else
                icon="document-text"
                :title="filtered ? 'No entries in this period' : 'No transactions yet'"
                :text="filtered ? 'Try a wider date range.' : 'Record the first deposit for this customer.'"
            >
                <Link v-if="!filtered" :href="route('transactions.create', { type: 'credit', customer: customer.id })" class="btn-primary">
                    <Icon name="arrow-down-circle" class="h-4 w-4" /> New Deposit
                </Link>
            </EmptyState>
        </div>

        <p class="mt-4 text-xs text-slate-400 print:text-slate-600">
            Registered {{ formatDate(customer.created_at) }}<span v-if="customer.registered_by"> by {{ customer.registered_by.name }}</span>.
            Printed {{ formatDate(new Date()) }}.
        </p>
    </AuthenticatedLayout>
</template>
