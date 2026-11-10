<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomerPicker from '@/Components/CustomerPicker.vue';
import PlanPicker from '@/Components/PlanPicker.vue';
import EntryFields from './Partials/EntryFields.vue';
import { isOnline } from '@/utils/pwa';

const props = defineProps({
    type: String,
    customer: Object,
    plans: Array,
    plan: String,
    collectors: Array,
    paymentMethods: Object,
    today: String,
});

const page = usePage();
const selectedCustomer = ref(props.customer);
const plans = ref(props.plans);
const loadingPlans = ref(false);

const form = useForm({
    customer_id: props.customer?.id ?? '',
    savings_plan_id: pickPlan(props.plans, props.type, props.plan),
    type: props.type,
    amount: '',
    date: props.today,
    payment_method: 'cash',
    collected_by: page.props.auth.user.id,
    description: '',
    close_plan: true,
});

const isDeposit = computed(() => form.type === 'credit');
const title = computed(() => (isDeposit.value ? 'New Deposit' : 'New Withdrawal'));
const usablePlans = computed(() => plans.value.filter((plan) => usableFor(form.type, plan)));
const selectedPlan = computed(() => usablePlans.value.find((plan) => plan.id === form.savings_plan_id) ?? null);

// A completed plan takes no deposits but can still be paid out.
function usableFor(type, plan) {
    return type === 'credit' ? plan.status === 'active' : plan.status === 'active' || plan.balance > 0;
}

function pickPlan(list, type, preferred = null) {
    const usable = list.filter((plan) => usableFor(type, plan));
    return (usable.find((plan) => plan.id === preferred) ?? usable[0])?.id ?? '';
}

watch(selectedCustomer, async (customer) => {
    form.customer_id = customer?.id ?? '';
    form.savings_plan_id = '';
    plans.value = [];
    if (!customer) return;

    loadingPlans.value = true;
    try {
        const { data } = await axios.get(route('customers.plans', customer.id));
        if (selectedCustomer.value?.id === customer.id) {
            plans.value = data;
            form.savings_plan_id = pickPlan(data, form.type);
        }
    } finally {
        loadingPlans.value = false;
    }
});

// Switching between deposit and withdrawal may rule out the chosen plan.
watch(
    () => form.type,
    () => {
        if (!selectedPlan.value) form.savings_plan_id = pickPlan(plans.value, form.type, form.savings_plan_id);
    },
);

// A deposit starts at one day's payment on the chosen plan.
watch(
    () => [form.savings_plan_id, form.type],
    () => {
        if (isDeposit.value && selectedPlan.value?.daily_amount) form.amount = selectedPlan.value.daily_amount;
        else if (!isDeposit.value) form.amount = '';
    },
    { immediate: true },
);

function submit() {
    form.transform((data) => ({ ...data, description: data.description || (isDeposit.value ? 'Daily Contribution' : 'Withdrawal') }))
        .post(route('transactions.store'));
}
</script>

<template>
    <AuthenticatedLayout :title="title" :back="customer ? route('customers.show', customer.id) : route('dashboard')">
        <form class="card max-w-2xl space-y-5 p-5 lg:p-8" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1" role="radiogroup" aria-label="Entry type">
                <button
                    type="button"
                    role="radio"
                    :aria-checked="isDeposit"
                    :class="['rounded-lg py-2 text-sm font-semibold transition', isDeposit ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500']"
                    @click="form.type = 'credit'"
                >
                    Deposit
                </button>
                <button
                    type="button"
                    role="radio"
                    :aria-checked="!isDeposit"
                    :class="['rounded-lg py-2 text-sm font-semibold transition', !isDeposit ? 'bg-white text-red-700 shadow-sm' : 'text-slate-500']"
                    @click="form.type = 'debit'"
                >
                    Withdrawal
                </button>
            </div>

            <div>
                <label class="label">Select customer</label>
                <CustomerPicker v-model="selectedCustomer" />
                <p v-if="form.errors.customer_id" class="field-error">Please choose a customer.</p>
            </div>

            <div v-if="selectedCustomer">
                <span class="label">Savings plan</span>
                <p v-if="loadingPlans" class="text-sm text-slate-500">Loading plans…</p>
                <PlanPicker v-else-if="usablePlans.length" v-model="form.savings_plan_id" :plans="usablePlans" :date="form.date" />
                <p v-else class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
                    {{ selectedCustomer.full_name }} has no {{ isDeposit ? 'plan taking deposits' : 'plan with money to pay out' }}.
                    <Link :href="route('plans.create', selectedCustomer.id)" class="font-semibold underline">Open a plan</Link> to record entries.
                </p>
                <p v-if="form.errors.savings_plan_id" class="field-error">{{ form.errors.savings_plan_id }}</p>
            </div>

            <EntryFields
                :form="form"
                :collectors="collectors"
                :payment-methods="paymentMethods"
                :today="today"
                :plan="selectedPlan"
                :balance="selectedPlan ? selectedPlan.balance : null"
            />

            <button
                type="submit"
                :class="['w-full py-3 text-base', isDeposit ? 'btn-primary' : 'btn-danger']"
                :disabled="form.processing || !form.savings_plan_id || !form.amount || !isOnline"
            >
                {{ form.processing ? 'Saving…' : isDeposit ? 'Save Deposit' : 'Save Withdrawal' }}
            </button>
        </form>
    </AuthenticatedLayout>
</template>
