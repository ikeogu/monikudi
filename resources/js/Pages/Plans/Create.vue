<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PlanFields from './Partials/PlanFields.vue';

const props = defineProps({
    customer: Object,
});

const form = useForm({
    name: '',
    daily_amount: '',
    target_months: '',
});
</script>

<template>
    <AuthenticatedLayout title="New Savings Plan" :subtitle="`${customer.reg_no} · ${customer.full_name}`" :back="route('customers.show', customer.id)">
        <form class="card max-w-3xl p-5 lg:p-8" @submit.prevent="form.post(route('plans.store', props.customer.id))">
            <PlanFields :form="form" />
            <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Link :href="route('customers.show', customer.id)" class="btn-secondary">Cancel</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">Open Plan</button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
