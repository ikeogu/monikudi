<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { monthLabel, naira, nairaTight } from '@/utils/format';

const props = defineProps({
    plan: { type: Object, required: true },
    customerId: { type: String, required: true },
    isAdmin: { type: Boolean, default: false },
});

const now = new Date();
const thisMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;

const active = computed(() => props.plan.status === 'active');
const completed = computed(() => props.plan.status === 'completed');
const closed = computed(() => props.plan.status === 'closed');
// Only a page from this month is "current"; otherwise the next payment opens a new one.
const page = computed(() => (props.plan.current_page?.month === thisMonth ? props.plan.current_page : null));
// A target is a 31-day page per month, counted in days saved.
const targetDays = computed(() => props.plan.target_days);
const daysLeft = computed(() => Math.max((targetDays.value ?? 0) - props.plan.days_saved, 0));
const progress = computed(() => (targetDays.value ? Math.min(100, (props.plan.days_saved / targetDays.value) * 100) : 0));
const showPages = ref(false);

// A payout mid-month ends the page, so a month can have more than one; number those.
function pageName(row) {
    const sameMonth = props.plan.pages.filter((p) => p.month === row.month).map((p) => p.number).sort((a, b) => a - b);
    return sameMonth.length > 1 ? `${monthLabel(row.month)} · page ${sameMonth.indexOf(row.number) + 1}` : monthLabel(row.month);
}
</script>

<template>
    <div :class="['card min-w-0 p-4 lg:p-5', closed && 'opacity-75']">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="flex items-center gap-2 font-semibold text-slate-900">
                    <span class="truncate">{{ plan.name }}</span>
                    <span v-if="completed" class="badge bg-emerald-50 text-emerald-700">Completed</span>
                    <span v-if="closed" class="badge bg-slate-100 text-slate-600">Closed</span>
                </p>
                <p class="text-xs text-slate-500">
                    {{ plan.daily_amount ? `${naira(plan.daily_amount)} a day` : 'No daily amount yet' }}
                </p>
            </div>
            <div class="text-right">
                <p class="text-lg font-bold text-emerald-700">{{ naira(plan.balance) }}</p>
                <p class="text-xs text-slate-500">Commission paid {{ naira(plan.commission) }}</p>
            </div>
        </div>

        <div v-if="!plan.daily_amount" class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">
            Moved over from before plans existed, so no commission is taken yet.
            <Link v-if="isAdmin" :href="route('plans.edit', plan.id)" class="font-semibold underline">Set the daily amount</Link>
            <span v-else>An admin can set the daily amount.</span>
        </div>

        <div v-else-if="active" class="mt-3">
            <div class="flex items-center justify-between text-xs">
                <span class="font-medium text-slate-700">
                    {{ page ? `${monthLabel(page.month)} page · Day ${page.day} of ${page.days}` : `No payment yet this month` }}
                </span>
                <span v-if="!page" class="text-amber-700">Next payment is commission</span>
            </div>
            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-emerald-500" :style="{ width: page ? `${(page.day / page.days) * 100}%` : '0%' }"></div>
            </div>
        </div>

        <div v-if="completed" class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
            Target reached, so this plan takes no more deposits.
            <template v-if="plan.balance > 0">Pay out {{ naira(plan.balance) }} to close it.</template>
        </div>

        <div v-if="targetDays" class="mt-3">
            <div class="flex justify-between text-xs text-slate-500">
                <span>{{ plan.target_months }}-month target</span>
                <span>
                    {{ Math.min(plan.days_saved, targetDays) }} of {{ targetDays }} days
                    <template v-if="daysLeft"> · {{ naira(daysLeft * plan.daily_amount) }} to go</template>
                </span>
            </div>
            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-brand-600" :style="{ width: `${progress}%` }"></div>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2 print:hidden">
            <Link v-if="active" :href="route('transactions.create', { type: 'credit', customer: customerId, plan: plan.id })" class="btn-primary px-3 py-1.5 text-xs">
                <Icon name="arrow-down-circle" class="h-4 w-4" /> Deposit
            </Link>
            <Link
                v-if="active || (completed && plan.balance > 0)"
                :href="route('transactions.create', { type: 'debit', customer: customerId, plan: plan.id })"
                :class="[completed ? 'btn-primary' : 'btn-secondary', 'px-3 py-1.5 text-xs']"
            >
                <Icon name="arrow-up-circle" class="h-4 w-4" /> {{ completed ? 'Pay out' : 'Withdraw' }}
            </Link>
            <Link v-if="isAdmin" :href="route('plans.edit', plan.id)" class="btn px-2 py-1.5 text-xs text-slate-500 hover:bg-slate-100">
                <Icon name="pencil" class="h-4 w-4" /> Edit
            </Link>
            <button v-if="plan.pages.length" type="button" class="btn ml-auto px-2 py-1.5 text-xs text-brand-700 hover:bg-brand-50" @click="showPages = !showPages">
                {{ showPages ? 'Hide' : 'Show' }} pages ({{ plan.pages.length }})
                <Icon name="chevron-down" :class="['h-4 w-4 transition', showPages && 'rotate-180']" />
            </button>
        </div>

        <!-- Phones: each page stacks its figures, since four money columns don't fit. -->
        <div v-if="showPages" class="-mx-4 mt-3 divide-y divide-slate-100 border-t border-slate-100 sm:hidden">
            <div v-for="row in plan.pages" :key="row.number" class="flex items-start justify-between gap-3 px-4 py-2.5 text-xs">
                <div>
                    <p class="font-medium text-slate-800">{{ pageName(row) }}</p>
                    <p class="text-slate-400">{{ row.days }} of {{ row.capacity }} days · saved {{ nairaTight(row.saved) }}</p>
                </div>
                <div class="text-right">
                    <p class="font-semibold text-emerald-700">{{ nairaTight(row.share) }}</p>
                    <p class="text-amber-600">−{{ nairaTight(row.commission) }} commission</p>
                </div>
            </div>
        </div>

        <div v-if="showPages" class="-mx-4 mt-3 hidden overflow-x-auto border-t border-slate-100 sm:block lg:-mx-5">
            <table class="min-w-full text-xs">
                <thead class="bg-slate-50 text-left font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2 lg:px-5">Month</th>
                        <th class="px-3 py-2 text-right">Saved</th>
                        <th class="px-3 py-2 text-right">Commission</th>
                        <th class="px-4 py-2 text-right lg:px-5">Customer's</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="row in plan.pages" :key="row.number">
                        <td class="px-4 py-2 text-slate-700 lg:px-5">
                            {{ pageName(row) }}
                            <span class="block text-slate-400">{{ row.days }} of {{ row.capacity }} days</span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2 text-right text-slate-900">{{ nairaTight(row.saved) }}</td>
                        <td class="whitespace-nowrap px-3 py-2 text-right text-amber-600">{{ nairaTight(row.commission) }}</td>
                        <td class="whitespace-nowrap px-4 py-2 text-right font-semibold text-emerald-700 lg:px-5">{{ nairaTight(row.share) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
