<script setup>
import { computed } from 'vue';
import { naira } from '@/utils/format';

const props = defineProps({
    form: { type: Object, required: true },
    // Registration names the field plan_name so it doesn't clash with the customer's details.
    nameField: { type: String, default: 'name' },
    nameRequired: { type: Boolean, default: true },
    amountLocked: { type: Boolean, default: false },
});

const dailyAmounts = [200, 500, 1000, 2000];
const targets = [
    { value: '', label: 'No target' },
    { value: 1, label: '1 month' },
    { value: 2, label: '2 months' },
    { value: 3, label: '3 months' },
    { value: 6, label: '6 months' },
];
const commission = computed(() => Number(props.form.daily_amount) || 0);
</script>

<template>
    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label :for="nameField" class="label">
                Plan name <span v-if="!nameRequired" class="font-normal text-slate-400">(optional)</span>
            </label>
            <input :id="nameField" v-model="form[nameField]" type="text" class="input" :required="nameRequired" maxlength="60" placeholder="e.g. House rent" />
            <p v-if="form.errors[nameField]" class="field-error">{{ form.errors[nameField] }}</p>
        </div>

        <div>
            <label for="daily_amount" class="label">Daily amount (₦)</label>
            <input
                id="daily_amount"
                v-model="form.daily_amount"
                type="number"
                inputmode="decimal"
                min="1"
                step="0.01"
                class="input font-semibold"
                required
                :disabled="amountLocked"
            />
            <div v-if="!amountLocked" class="mt-2 flex flex-wrap gap-2">
                <button
                    v-for="amount in dailyAmounts"
                    :key="amount"
                    type="button"
                    :class="[
                        'rounded-full px-3 py-1 text-xs font-medium ring-1',
                        Number(form.daily_amount) === amount ? 'bg-brand-800 text-white ring-brand-800' : 'text-slate-600 ring-slate-300 hover:bg-slate-50',
                    ]"
                    @click="form.daily_amount = amount"
                >
                    {{ naira(amount).replace('.00', '') }}
                </button>
            </div>
            <p v-if="amountLocked" class="mt-1.5 text-xs text-slate-500">
                Fixed now that deposits have counted toward pages. To change it, close this plan and open a new one.
            </p>
            <p v-else-if="commission" class="mt-1.5 text-xs text-slate-500">
                The first payment on each month's page ({{ naira(commission) }}) is the commission; every payment must be at least this much.
            </p>
            <p v-if="form.errors.daily_amount" class="field-error">{{ form.errors.daily_amount }}</p>
        </div>

        <div class="sm:col-span-2">
            <span class="label">Saving for <span class="font-normal text-slate-400">(optional)</span></span>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="target in targets"
                    :key="target.label"
                    type="button"
                    :class="[
                        'rounded-xl px-3.5 py-2 text-sm font-medium ring-1',
                        String(form.target_months ?? '') === String(target.value) ? 'bg-brand-50 text-brand-800 ring-2 ring-brand-600' : 'text-slate-600 ring-slate-300 hover:bg-slate-50',
                    ]"
                    @click="form.target_months = target.value"
                >
                    {{ target.label }}
                </button>
            </div>
            <p v-if="form.target_months" class="mt-1.5 text-xs text-slate-500">
                That's {{ form.target_months * 31 }} days of saving (31 a month). Once reached, the plan takes no more deposits and closes when paid out.
            </p>
            <p v-if="form.errors.target_months" class="field-error">{{ form.errors.target_months }}</p>
        </div>
    </div>
</template>
