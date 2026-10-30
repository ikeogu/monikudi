<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DepositsChart from '@/Components/DepositsChart.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Icon from '@/Components/Icon.vue';
import StatCard from '@/Components/StatCard.vue';
import TransactionItem from '@/Components/TransactionItem.vue';
import { formatDate, naira } from '@/utils/format';

const props = defineProps({
    today: String,
    stats: Object,
    chart: Object,
    recentTransactions: Array,
    topCustomers: Array,
});

const isAdmin = computed(() => usePage().props.auth.isAdmin);
// Beside the recent-transactions list on wide screens, a taller chart fills the row.
const chartHeight = window.matchMedia('(min-width: 1280px)').matches ? 400 : 260;

const cards = computed(() => {
    const s = props.stats;
    const scope = isAdmin.value ? '' : 'My ';
    return [
        { label: 'Total Customers', value: s.customers.toLocaleString(), hint: 'Registered customers', icon: 'users', tone: 'brand' },
        isAdmin.value && { label: 'Total Savings Balance', value: naira(s.savingsBalance), hint: 'Held for all customers', icon: 'banknotes', tone: 'green' },
        { label: `${scope}Today's Deposits`, value: naira(s.todayDeposits), hint: `${s.todayDepositsCount} deposit${s.todayDepositsCount === 1 ? '' : 's'} today`, icon: 'arrow-down-circle', tone: 'green' },
        { label: `${scope}Today's Withdrawals`, value: naira(s.todayWithdrawals), hint: `${s.todayWithdrawalsCount} withdrawal${s.todayWithdrawalsCount === 1 ? '' : 's'} today`, icon: 'arrow-up-circle', tone: 'red' },
        { label: `${scope}Total Deposits`, value: naira(s.totalDeposits), hint: 'All time', icon: 'wallet', tone: 'brand' },
        { label: `${scope}Total Withdrawals`, value: naira(s.totalWithdrawals), hint: 'All time', icon: 'clock', tone: 'amber' },
        isAdmin.value && { label: 'Active Staff', value: s.activeStaff, hint: 'Admins and collectors', icon: 'user-group', tone: 'slate' },
    ].filter(Boolean);
});
</script>

<template>
    <AuthenticatedLayout title="Dashboard">
        <template #actions>
            <span class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-medium text-brand-800 ring-1 ring-slate-200">
                <Icon name="calendar" class="h-4 w-4" />
                Today, {{ formatDate(today) }}
            </span>
            <Link :href="route('transactions.create', { type: 'credit' })" class="btn-primary hidden lg:inline-flex">
                <Icon name="plus" class="h-4 w-4" /> New Deposit
            </Link>
        </template>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-5">
            <StatCard v-for="card in cards" :key="card.label" v-bind="card" />
        </div>

        <div class="mt-5 grid gap-5 lg:mt-6 xl:grid-cols-3">
            <section class="card min-w-0 p-4 lg:p-6 xl:col-span-2">
                <h2 class="font-semibold text-slate-900">Deposits &amp; Withdrawals ({{ chart.month }})</h2>
                <div class="mt-4">
                    <DepositsChart :days="chart.days" :height="chartHeight" />
                </div>
            </section>

            <section class="card min-w-0 overflow-hidden">
                <div class="flex items-center justify-between px-4 pt-4 lg:px-5 lg:pt-5">
                    <h2 class="font-semibold text-slate-900">Recent Transactions</h2>
                    <Link :href="route('transactions.index')" class="text-sm font-medium text-brand-700 hover:text-brand-900">View All</Link>
                </div>
                <div v-if="recentTransactions.length" class="mt-2 divide-y divide-slate-100">
                    <TransactionItem v-for="transaction in recentTransactions" :key="transaction.id" :transaction="transaction" />
                </div>
                <EmptyState v-else icon="arrows-right-left" title="No transactions yet" text="Deposits and withdrawals will show up here." />
            </section>
        </div>

        <section v-if="isAdmin && topCustomers.length" class="card mt-5 overflow-hidden lg:mt-6">
            <h2 class="px-4 pt-4 font-semibold text-slate-900 lg:px-6 lg:pt-5">Top Customers by Balance</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 lg:px-6">Customer ID</th>
                            <th class="px-4 py-3">Customer Name</th>
                            <th class="px-4 py-3 text-right lg:px-6">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="customer in topCustomers" :key="customer.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-900 lg:px-6">
                                <Link :href="route('customers.show', customer.id)">{{ customer.reg_no }}</Link>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ customer.full_name }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-900 lg:px-6">{{ naira(customer.balance) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
