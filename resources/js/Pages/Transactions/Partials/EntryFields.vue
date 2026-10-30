<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { naira } from '@/utils/format';

const props = defineProps({
    form: { type: Object, required: true },
    collectors: { type: Array, required: true },
    paymentMethods: { type: Object, required: true },
    today: { type: String, required: true },
    // Available balance for withdrawals, when a customer is selected.
    balance: { type: Number, default: null },
});

const quickAmounts = [200, 500, 1000, 2000, 5000];
const isAdmin = computed(() => usePage().props.auth.isAdmin);
const overdrawn = computed(
    () => props.form.type === 'debit' && props.balance !== null && Number(props.form.amount) > props.balance,
);
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
                min="1"
                step="0.01"
                class="input text-lg font-semibold"
                placeholder="0.00"
                required
            />
            <div class="mt-2 flex flex-wrap gap-2">
                <button
                    v-for="amount in quickAmounts"
                    :key="amount"
                    type="button"
                    :class="[
                        'rounded-full px-3 py-1 text-xs font-medium ring-1',
                        Number(form.amount) === amount ? 'bg-brand-800 text-white ring-brand-800' : 'bg-white text-slate-600 ring-slate-300 hover:bg-slate-50',
                    ]"
                    @click="form.amount = amount"
                >
                    {{ naira(amount).replace('.00', '') }}
                </button>
            </div>
            <p v-if="overdrawn" class="field-error">This is more than the available balance of {{ naira(balance) }}.</p>
            <p v-if="form.errors.amount" class="field-error">{{ form.errors.amount }}</p>
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
