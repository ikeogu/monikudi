<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PlanFields from './Partials/PlanFields.vue';

const props = defineProps({
    plan: Object,
    customer: Object,
    amountLocked: Boolean,
});

const form = useForm({
    name: props.plan.name,
    daily_amount: props.plan.daily_amount ?? '',
    target_months: props.plan.target_months ?? '',
});

function close() {
    if (confirm(`Close the ${props.plan.name} plan? It must have no money left on it.`)) {
        router.post(route('plans.close', props.plan.id));
    }
}
</script>

<template>
    <AuthenticatedLayout title="Edit Savings Plan" :subtitle="`${customer.reg_no} · ${customer.full_name}`" :back="route('customers.show', customer.id)">
        <form class="card max-w-3xl p-5 lg:p-8" @submit.prevent="form.put(route('plans.update', plan.id))">
            <p v-if="plan.daily_amount === null" class="mb-5 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
                This plan was moved over from before plans existed. Set its daily amount to start counting pages; the next payment will be the first of a page, so it goes to commission.
            </p>
            <PlanFields :form="form" :amount-locked="amountLocked" />
            <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                <button v-if="plan.status !== 'closed'" type="button" class="btn text-red-600 hover:bg-red-50 sm:mr-auto" @click="close">Close plan</button>
                <Link :href="route('customers.show', customer.id)" class="btn-secondary">Cancel</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">Save Changes</button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
