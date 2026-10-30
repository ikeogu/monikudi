<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StaffForm from './Partials/StaffForm.vue';

const props = defineProps({
    member: Object,
    roles: Array,
});

const form = useForm({
    name: props.member.name,
    email: props.member.email,
    phone: props.member.phone || '',
    role: props.member.role,
    password: '',
    password_confirmation: '',
    is_active: props.member.is_active,
});
</script>

<template>
    <AuthenticatedLayout title="Edit Staff" :subtitle="member.name" :back="route('staff.index')">
        <form class="card max-w-3xl p-5 lg:p-8" @submit.prevent="form.put(route('staff.update', member.id))">
            <StaffForm :form="form" :roles="roles" editing />
            <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Link :href="route('staff.index')" class="btn-secondary">Cancel</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">Save Changes</button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
