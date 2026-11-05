<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomerForm from './Partials/CustomerForm.vue';
import PlanFields from '../Plans/Partials/PlanFields.vue';

defineProps({
    suggestedRegNo: String,
});

const form = useForm({
    first_name: '',
    last_name: '',
    phone: '',
    reg_no: '',
    email: '',
    address: '',
    plan_name: '',
    daily_amount: '',
    target_months: '',
});
</script>

<template>
    <AuthenticatedLayout title="Register Customer" :back="route('customers.index')">
        <form class="card max-w-3xl p-5 lg:p-8" @submit.prevent="form.post(route('customers.store'))">
            <CustomerForm :form="form" :suggested-reg-no="suggestedRegNo" />

            <div class="mt-8 border-t border-slate-100 pt-6">
                <h2 class="font-semibold text-slate-900">Savings plan</h2>
                <p class="mb-5 mt-1 text-sm text-slate-500">You can add more plans for this customer later, e.g. one per goal.</p>
                <PlanFields :form="form" name-field="plan_name" :name-required="false" />
            </div>

            <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Link :href="route('customers.index')" class="btn-secondary">Cancel</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">Register Customer</button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
