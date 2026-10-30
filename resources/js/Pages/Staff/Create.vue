<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StaffForm from './Partials/StaffForm.vue';

defineProps({
    roles: Array,
});

const form = useForm({
    name: '',
    email: '',
    phone: '',
    role: 'collector',
    password: '',
    password_confirmation: '',
    is_active: true,
});
</script>

<template>
    <AuthenticatedLayout title="Add Staff" :back="route('staff.index')">
        <form class="card max-w-3xl p-5 lg:p-8" @submit.prevent="form.post(route('staff.store'))">
            <StaffForm :form="form" :roles="roles" />
            <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Link :href="route('staff.index')" class="btn-secondary">Cancel</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">Add to Team</button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
