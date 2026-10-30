<script setup>
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CustomerForm from './Partials/CustomerForm.vue';

const props = defineProps({
    customer: Object,
});

const form = useForm({
    first_name: props.customer.first_name,
    last_name: props.customer.last_name,
    phone: props.customer.phone || '',
    reg_no: props.customer.reg_no || '',
    email: props.customer.email || '',
    address: props.customer.address || '',
});

const isAdmin = usePage().props.auth.isAdmin;

function destroy() {
    if (confirm(`Delete ${props.customer.full_name}? Their history is kept but they will no longer appear in lists.`)) {
        router.delete(route('customers.destroy', props.customer.id));
    }
}
</script>

<template>
    <AuthenticatedLayout title="Edit Customer" :subtitle="customer.reg_no" :back="route('customers.show', customer.id)">
        <form class="card max-w-3xl p-5 lg:p-8" @submit.prevent="form.put(route('customers.update', customer.id))">
            <CustomerForm :form="form" :can-generate="!customer.reg_no" />
            <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                <button v-if="isAdmin" type="button" class="btn text-red-600 hover:bg-red-50 sm:mr-auto" @click="destroy">Delete customer</button>
                <Link :href="route('customers.show', customer.id)" class="btn-secondary">Cancel</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">Save Changes</button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
