<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { monthLabel, naira, PAGE_DAYS } from '@/utils/format';

const props = defineProps({
    form: { type: Object, required: true },
    collectors: { type: Array, required: true },
    paymentMethods: { type: Object, required: true },
    today: { type: String, required: true },
    // The plan the entry goes on, when known: its daily amount, balance and current page.
    plan: { type: Object, default: null },
    // Available balance for withdrawals.
    balance: { type: Number, default: null },
});

const isAdmin = computed(() => usePage().props.auth.isAdmin);
const isDeposit = computed(() => props.form.type === 'credit');
const daily = computed(() => props.plan?.daily_amount ?? null);
const amount = computed(() => Number(props.form.amount) || 0);
const overdrawn = computed(() => !isDeposit.value && props.balance !== null && amount.value > props.balance);
const collectsAll = computed(() => !isDeposit.value && props.balance > 0 && amount.value === props.balance);

const month = computed(() => (props.form.date || props.today).slice(0, 7));
// The day reached on this month's page so far, or null when the next payment starts a new page.
const dayOnPage = computed(() => {
    const page = props.plan?.current_page;
    return page && page.month === month.value && page.day < page.days ? page.day : null;
});

const quickAmounts = computed(() => {
    if (!isDeposit.value) return [];
    if (!daily.value) return [200, 500, 1000, 2000, 5000].map((value) => ({ value, label: naira(value).replace('.00', '') }));

    const rest = PAGE_DAYS - (dayOnPage.value ?? 0);
    return [
        ...[1, 5, 10].map((days) => ({ value: days * daily.value, label: `${days} day${days === 1 ? '' : 's'}` })),
        { value: rest * daily.value, label: `Fill the page (${rest} days)` },
    ];
});

// What this payment does to the plan's page, worked out the same way the ledger does.
const pageNote = computed(() => {
    if (!isDeposit.value || !daily.value || amount.value < daily.value) return null;

    const days = Math.floor(amount.value / daily.value);
    const name = monthLabel(month.value);
    if (dayOnPage.value === null) {
        return {
            commission: true,
            text: `First payment of ${name}'s page: ${naira(daily.value)} goes to commission. Counts as ${days} day${days === 1 ? '' : 's'}.`,
        };
    }
    if (dayOnPage.value + days > PAGE_DAYS) {
        return { commission: true, text: `Fills ${name}'s page and starts another, so ${naira(daily.value)} goes to commission.` };
    }
    return { commission: false, text: `Counts as ${days} day${days === 1 ? '' : 's'} · Day ${dayOnPage.value + days} of ${PAGE_DAYS}.` };
});
</script>

<template>
    <div class="space-y-5">
        <div>
            <label for="amount" class="label">Amount (₦)</label>
            <input
                id="amount"
                v-model="form.amount"
                type="number"
                inputmode="decimal"
                :min="isDeposit && daily ? daily : 1"
                step="0.01"
                class="input text-lg font-semibold"
                placeholder="0.00"
                required
            />
            <div class="mt-2 flex flex-wrap gap-2">
                <button
                    v-for="quick in quickAmounts"
                    :key="quick.label"
                    type="button"
                    :class="[
                        'rounded-full px-3 py-1 text-xs font-medium ring-1',
                        amount === quick.value ? 'bg-brand-800 text-white ring-brand-800' : 'bg-white text-slate-600 ring-slate-300 hover:bg-slate-50',
                    ]"
                    @click="form.amount = quick.value"
                >
                    {{ quick.label }}
                </button>
                <button
                    v-if="!isDeposit && balance > 0"
                    type="button"
                    :class="[
                        'rounded-full px-3 py-1 text-xs font-medium ring-1',
                        collectsAll ? 'bg-brand-800 text-white ring-brand-800' : 'bg-white text-slate-600 ring-slate-300 hover:bg-slate-50',
                    ]"
                    @click="form.amount = balance"
                >
                    Collect everything ({{ naira(balance) }})
                </button>
            </div>

            <p v-if="isDeposit && daily && amount > 0 && amount < daily" class="field-error">The smallest payment on this plan is {{ naira(daily) }}.</p>
            <p
                v-else-if="pageNote"
                :class="['mt-2 rounded-lg px-3 py-2 text-sm', pageNote.commission ? 'bg-amber-50 text-amber-900' : 'bg-emerald-50 text-emerald-800']"
            >
                {{ pageNote.text }}
            </p>
            <p v-if="overdrawn" class="field-error">This is more than the available balance of {{ naira(balance) }}.</p>
            <p v-if="form.errors.amount" class="field-error">{{ form.errors.amount }}</p>

            <label v-if="collectsAll && 'close_plan' in form" class="mt-3 flex items-start gap-3 rounded-xl bg-slate-50 p-3">
                <input v-model="form.close_plan" type="checkbox" class="mt-0.5 rounded border-slate-300 text-brand-700 focus:ring-brand-500" />
                <span class="text-sm">
                    <span class="block font-semibold text-slate-900">Close {{ plan?.name ?? 'this plan' }} after paying out</span>
                    <span class="block text-xs text-slate-500">Collecting everything ends this month's page either way; saving again starts a new page with commission.</span>
                </span>
            </label>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="date" class="label">Transaction date</label>
                <input id="date" v-model="form.date" type="date" :max="today" class="input" required />
                <p v-if="form.errors.date" class="field-error">{{ form.errors.date }}</p>
            </div>
            <div>
                <label for="payment_method" class="label">Payment method</label>
                <select id="payment_method" v-model="form.payment_method" class="input">
                    <option v-for="(label, value) in paymentMethods" :key="value" :value="value">{{ label }}</option>
                </select>
                <p v-if="form.errors.payment_method" class="field-error">{{ form.errors.payment_method }}</p>
            </div>
        </div>

        <div>
            <label for="collected_by" class="label">{{ form.type === 'debit' ? 'Paid out by' : 'Collected by' }}</label>
            <select id="collected_by" v-model="form.collected_by" class="input" :disabled="!isAdmin">
                <option v-for="collector in collectors" :key="collector.id" :value="collector.id">{{ collector.name }}</option>
            </select>
            <p v-if="form.errors.collected_by" class="field-error">{{ form.errors.collected_by }}</p>
        </div>

        <div>
            <label for="description" class="label">Remarks <span class="font-normal text-slate-400">(optional)</span></label>
            <input
                id="description"
                v-model="form.description"
                type="text"
                class="input"
                :placeholder="form.type === 'debit' ? 'Withdrawal' : 'Daily Contribution'"
            />
            <p v-if="form.errors.description" class="field-error">{{ form.errors.description }}</p>
        </div>
    </div>
</template>
