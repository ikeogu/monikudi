<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomerPicker from '@/Components/CustomerPicker.vue';
import EntryFields from './Partials/EntryFields.vue';
import { isOnline } from '@/utils/pwa';

const props = defineProps({
    type: String,
    customer: Object,
    collectors: Array,
    paymentMethods: Object,
    today: String,
});

const page = usePage();
const selectedCustomer = ref(props.customer);

const form = useForm({
    customer_id: props.customer?.id ?? '',
    type: props.type,
    amount: '',
    date: props.today,
    payment_method: 'cash',
    collected_by: page.props.auth.user.id,
    description: '',
});

watch(selectedCustomer, (customer) => (form.customer_id = customer?.id ?? ''));

const isDeposit = computed(() => form.type === 'credit');
const title = computed(() => (isDeposit.value ? 'New Deposit' : 'New Withdrawal'));

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

            <EntryFields
                :form="form"
                :collectors="collectors"
                :payment-methods="paymentMethods"
                :today="today"
                :balance="selectedCustomer ? selectedCustomer.balance : null"
            />

            <button
                type="submit"
                :class="['w-full py-3 text-base', isDeposit ? 'btn-primary' : 'btn-danger']"
                :disabled="form.processing || !form.customer_id || !form.amount || !isOnline"
            >
                {{ form.processing ? 'Saving…' : isDeposit ? 'Save Deposit' : 'Save Withdrawal' }}
            </button>
        </form>
    </AuthenticatedLayout>
</template>
