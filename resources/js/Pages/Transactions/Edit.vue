<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomerPicker from '@/Components/CustomerPicker.vue';
import EntryFields from './Partials/EntryFields.vue';

const props = defineProps({
    transaction: Object,
    collectors: Array,
    paymentMethods: Object,
    today: String,
});

const form = useForm({
    type: props.transaction.type,
    amount: props.transaction.amount,
    date: props.transaction.date.slice(0, 10),
    payment_method: props.transaction.payment_method || 'cash',
    collected_by: props.transaction.collected_by,
    description: props.transaction.description || '',
});

function destroy() {
    if (confirm('Delete this entry? The customer balance will be adjusted.')) {
        router.delete(route('transactions.destroy', props.transaction.id));
    }
}
</script>

<template>
    <AuthenticatedLayout title="Edit Entry" :subtitle="transaction.receipt_no" :back="route('transactions.show', transaction.id)">
        <form class="card max-w-2xl space-y-5 p-5 lg:p-8" @submit.prevent="form.put(route('transactions.update', transaction.id))">
            <div class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
                Changing the amount or type adjusts {{ transaction.customer.first_name }}'s balance by the difference.
            </div>

            <div>
                <label class="label">Customer</label>
                <CustomerPicker :model-value="transaction.customer" locked />
            </div>

            <div>
                <label for="type" class="label">Type</label>
                <select id="type" v-model="form.type" class="input">
                    <option value="credit">Deposit</option>
                    <option value="debit">Withdrawal</option>
                </select>
            </div>

            <EntryFields :form="form" :collectors="collectors" :payment-methods="paymentMethods" :today="today" />

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                <button type="button" class="btn text-red-600 hover:bg-red-50 sm:mr-auto" @click="destroy">Delete entry</button>
                <Link :href="route('transactions.show', transaction.id)" class="btn-secondary">Cancel</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">Save Changes</button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
