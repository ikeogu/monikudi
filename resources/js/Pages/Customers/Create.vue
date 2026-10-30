<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomerForm from './Partials/CustomerForm.vue';

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
});
</script>

<template>
    <AuthenticatedLayout title="Register Customer" :back="route('customers.index')">
        <form class="card max-w-3xl p-5 lg:p-8" @submit.prevent="form.post(route('customers.store'))">
            <CustomerForm :form="form" :suggested-reg-no="suggestedRegNo" />
            <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Link :href="route('customers.index')" class="btn-secondary">Cancel</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">Register Customer</button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
