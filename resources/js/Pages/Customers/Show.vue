<script setup>
import { computed, nextTick, reactive, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import Dropdown from '@/Components/Dropdown.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Icon from '@/Components/Icon.vue';
import PlanCard from '@/Components/PlanCard.vue';
import ThermalSlip from '@/Components/ThermalSlip.vue';
import { entryTone, formatDate, formatShortDate, formatTime, isCommission, isDeposit, naira, nairaTight, signedNaira, typeLabel } from '@/utils/format';
import { paper, setPaper, SLIP_PAGE_CSS, usePageStyle } from '@/utils/print';
import { statementText, whatsappUrl } from '@/utils/share';

const props = defineProps({
    customer: Object,
    plans: Array,
    summary: Object,
    transactions: Array,
    filters: Object,
});

const page = usePage();
const isAdmin = computed(() => page.props.auth.isAdmin);
const business = computed(() => page.props.business);

const range = reactive({ from: props.filters.from || '', to: props.filters.to || '' });
const filtered = computed(() => Boolean(props.filters.from || props.filters.to));
const selectedPlan = computed(() => props.plans.find((plan) => plan.id === props.filters.plan) ?? null);
// With several plans and no plan chosen, each entry says which plan it's on.
const showPlanColumn = computed(() => props.plans.length > 1 && !selectedPlan.value);
// Older versions of the app could leave the stored balance out of step with the entries.
const outOfSync = computed(() => Math.abs(props.summary.historyBalance - props.summary.balance) >= 0.01);

const shareUrl = computed(() =>
    whatsappUrl(props.customer.phone, statementText(props.customer, props.summary, props.transactions, business.value)),
);

function visit(params) {
    const query = { plan: props.filters.plan, from: range.from, to: range.to, ...params };
    router.get(
        route('customers.show', props.customer.id),
        Object.fromEntries(Object.entries(query).filter(([, value]) => value)),
        { preserveScroll: true, preserveState: true },
    );
}

function applyRange() {
    visit({});
}

function clearRange() {
    range.from = '';
    range.to = '';
    visit({});
}

const excelUrl = computed(() =>
    route('customers.export', { customer: props.customer.id, ...Object.fromEntries(Object.entries(props.filters).filter(([, value]) => value)) }),
);

// Thermal slip: the listed entries oldest first, with totals for just those entries.
const slip = ref(false);
const slipEntries = computed(() => [...props.transactions].reverse());
const slipTotals = computed(() =>
    props.transactions.reduce(
        (totals, t) => {
            totals[isDeposit(t) ? 'deposits' : isCommission(t) ? 'commission' : 'withdrawals'] += Number(t.amount);
            return totals;
        },
        { deposits: 0, withdrawals: 0, commission: 0 },
    ),
);
const slipBalance = computed(() => {
    if (!filtered.value) return props.summary.balance;
    return props.transactions.length ? props.transactions[0].running_balance : props.summary.opening;
});

usePageStyle(() => (slip.value ? SLIP_PAGE_CSS : ''));

function slipDate(date) {
    return formatDate(date, { day: '2-digit', month: '2-digit', year: '2-digit' });
}

async function printSlip(size) {
    setPaper(size);
    slip.value = true;
    await nextTick();
    window.addEventListener('afterprint', () => (slip.value = false), { once: true });
    window.print();
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

        <!-- Thermal slip: only printed, and only when a slip was chosen -->
        <div v-if="slip" class="hidden print:block">
            <ThermalSlip :paper="paper" heading="STATEMENT" :reference="filtered ? `${formatDate(filters.from) || 'Start'} – ${formatDate(filters.to) || 'Today'}` : 'All entries'">
                <dl class="mt-2 space-y-0.5 border-y border-dashed border-black py-2">
                    <div class="flex justify-between gap-2"><dt>Customer ID</dt><dd class="text-right">{{ customer.reg_no }}</dd></div>
                    <div class="flex justify-between gap-2"><dt>Name</dt><dd class="text-right">{{ customer.full_name }}</dd></div>
                    <div v-if="customer.phone" class="flex justify-between gap-2"><dt>Phone</dt><dd class="text-right">{{ customer.phone }}</dd></div>
                    <div v-if="selectedPlan" class="flex justify-between gap-2"><dt>Plan</dt><dd class="text-right">{{ selectedPlan.name }}</dd></div>
                </dl>

                <table class="w-full tabular-nums">
                    <thead>
                        <tr class="border-b border-dashed border-black">
                            <th class="py-1 text-left font-semibold">Date</th>
                            <th class="py-1 text-right font-semibold">Amount</th>
                            <th class="py-1 text-right font-semibold">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="filtered">
                            <td class="py-0.5">Opening</td>
                            <td></td>
                            <td class="whitespace-nowrap py-0.5 text-right">{{ nairaTight(summary.opening) }}</td>
                        </tr>
                        <tr v-for="t in slipEntries" :key="t.id">
                            <td class="whitespace-nowrap py-0.5 pr-1">{{ isCommission(t) ? 'Commission' : slipDate(t.date) }}</td>
                            <td class="whitespace-nowrap py-0.5 text-right">{{ isDeposit(t) ? '+' : '-' }}{{ nairaTight(t.amount) }}</td>
                            <td class="whitespace-nowrap py-0.5 pl-1 text-right">{{ nairaTight(t.running_balance) }}</td>
                        </tr>
                        <tr v-if="!slipEntries.length">
                            <td colspan="3" class="py-2 text-center">No entries</td>
                        </tr>
                    </tbody>
                </table>

                <dl class="mt-1 space-y-0.5 border-t border-dashed border-black py-2">
                    <div class="flex justify-between gap-2"><dt>Deposits</dt><dd>{{ naira(slipTotals.deposits) }}</dd></div>
                    <div class="flex justify-between gap-2"><dt>Withdrawals</dt><dd>{{ naira(slipTotals.withdrawals) }}</dd></div>
                    <div v-if="slipTotals.commission" class="flex justify-between gap-2"><dt>Commission</dt><dd>{{ naira(slipTotals.commission) }}</dd></div>
                    <div class="flex justify-between gap-2 font-bold"><dt>{{ filtered ? 'Closing balance' : 'Balance' }}</dt><dd>{{ naira(slipBalance) }}</dd></div>
                    <p class="pt-1 text-center text-[0.85em]">Printed {{ formatDate(new Date()) }}, {{ formatTime(new Date()) }}</p>
                </dl>
            </ThermalSlip>
        </div>

        <div :class="{ 'print:hidden': slip }">
            <!-- Printed header -->
            <div class="mb-6 hidden border-b border-slate-300 pb-4 print:block">
                <p class="text-xl font-bold">{{ business.business_name }}</p>
                <p class="text-sm">{{ business.address }} {{ business.phone }}</p>
                <p class="mt-2 font-semibold">Customer Statement{{ filtered ? `: ${formatDate(filters.from) || 'start'} – ${formatDate(filters.to) || 'today'}` : '' }}</p>
            </div>

            <div class="card p-4 lg:p-6 print:shadow-none print:ring-0">
                <!-- On phones the action icons wrap onto their own row so the name keeps its width. -->
                <div class="flex flex-wrap items-start gap-x-4 gap-y-3">
                    <Avatar :name="customer.full_name" size="h-14 w-14 text-lg lg:h-16 lg:w-16" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-brand-700">{{ customer.reg_no }}</p>
                        <p class="truncate text-lg font-bold text-slate-900">{{ customer.full_name }}</p>
                        <p class="text-sm text-slate-500">{{ customer.phone || 'No phone number' }}</p>
                        <p v-if="customer.address" class="text-sm text-slate-500">{{ customer.address }}</p>
                    </div>
                    <div class="-ml-2 flex w-full gap-1 sm:ml-0 sm:w-auto print:hidden">
                        <a :href="shareUrl" target="_blank" rel="noopener" class="rounded-lg p-2 text-[#1ebe5b] hover:bg-emerald-50" title="Share on WhatsApp">
                            <Icon name="whatsapp" class="h-5 w-5" />
                        </a>
                        <Link :href="route('collections.index', { customer: customer.id })" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="Daily collections">
                            <Icon name="calendar" class="h-5 w-5" />
                        </Link>
                        <a :href="excelUrl" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="Download Excel">
                            <Icon name="arrow-down-tray" class="h-5 w-5" />
                        </a>
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="Print statement">
                                    <Icon name="printer" class="h-5 w-5" />
                                </button>
                            </template>
                            <template #content>
                                <p class="px-4 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Print</p>
                                <button
                                    v-for="size in ['58', '80']"
                                    :key="size"
                                    type="button"
                                    class="flex w-full items-center justify-between px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-100"
                                    @click="printSlip(size)"
                                >
                                    Slip · {{ size }}mm
                                    <Icon v-if="paper === size" name="check-circle" class="h-4 w-4 text-brand-700" />
                                </button>
                                <button type="button" class="block w-full border-t border-slate-100 px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-100" @click="printStatement">
                                    Full page (A4)
                                </button>
                            </template>
                        </Dropdown>
                        <Link :href="route('customers.edit', customer.id)" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="Edit customer">
                            <Icon name="pencil" class="h-5 w-5" />
                        </Link>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-2 lg:grid-cols-4 lg:gap-4">
                    <div class="rounded-xl bg-slate-50 p-3 lg:p-4">
                        <p class="text-xs text-slate-500">Total Deposits</p>
                        <p class="mt-1 text-sm font-bold text-slate-900 lg:text-xl">{{ naira(summary.deposits) }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3 lg:p-4">
                        <p class="text-xs text-slate-500">Total Withdrawals</p>
                        <p class="mt-1 text-sm font-bold text-slate-900 lg:text-xl">{{ naira(summary.withdrawals) }}</p>
                    </div>
                    <div class="rounded-xl bg-amber-50 p-3 lg:p-4">
                        <p class="text-xs text-amber-700">Commission</p>
                        <p class="mt-1 text-sm font-bold text-amber-700 lg:text-xl">{{ naira(summary.commission) }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 p-3 lg:p-4">
                        <p class="text-xs text-emerald-700">{{ selectedPlan ? `${selectedPlan.name} Balance` : 'Current Balance' }}</p>
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

            <section class="mt-5 print:hidden">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-semibold text-slate-900">Savings plans</h2>
                    <Link :href="route('plans.create', customer.id)" class="btn-secondary px-3 py-1.5 text-sm">
                        <Icon name="plus" class="h-4 w-4" /> New plan
                    </Link>
                </div>
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <PlanCard v-for="plan in plans" :key="plan.id" :plan="plan" :customer-id="customer.id" :is-admin="isAdmin" />
                </div>
            </section>

            <div class="card mt-5 overflow-hidden print:mt-4 print:shadow-none print:ring-0">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-4 lg:flex-row lg:items-end lg:px-6">
                    <div class="flex-1">
                        <h2 class="font-semibold text-slate-900">Transaction History{{ selectedPlan ? `: ${selectedPlan.name}` : '' }}</h2>
                        <div v-if="plans.length > 1" class="mt-2 flex flex-wrap gap-1 print:hidden">
                            <button
                                v-for="option in [{ id: '', name: 'All plans' }, ...plans]"
                                :key="option.id"
                                type="button"
                                :class="[
                                    'rounded-full px-3 py-1 text-xs font-medium',
                                    (filters.plan || '') === option.id ? 'bg-brand-800 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50',
                                ]"
                                @click="visit({ plan: option.id })"
                            >
                                {{ option.name }}
                            </button>
                        </div>
                    </div>
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
                                <p :class="['text-sm', entryTone(t)]">{{ typeLabel(t.type) }}</p>
                                <p v-if="(showPlanColumn && t.plan) || t.collector" class="text-xs text-slate-400">
                                    {{ [showPlanColumn && t.plan?.name, t.collector?.name].filter(Boolean).join(' · ') }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p :class="['text-sm font-semibold', entryTone(t)]">{{ signedNaira(t) }}</p>
                                <p class="text-xs text-slate-500">Balance: {{ naira(t.running_balance) }}</p>
                            </div>
                        </Link>
                    </div>

                    <table class="hidden min-w-full text-sm md:table print:table">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-6 py-3">Date</th>
                                <th class="px-4 py-3">Receipt</th>
                                <th v-if="showPlanColumn" class="px-4 py-3">Plan</th>
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
                                <td v-if="showPlanColumn" class="px-4 py-3 text-slate-600">{{ t.plan?.name }}</td>
                                <td :class="['px-4 py-3 font-medium', entryTone(t)]">{{ typeLabel(t.type) }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ isCommission(t) ? '—' : t.collector?.name || '—' }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ t.description || '—' }}</td>
                                <td :class="['whitespace-nowrap px-4 py-3 text-right font-semibold', entryTone(t)]">
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
        </div>
    </AuthenticatedLayout>
</template>
