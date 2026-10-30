<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SettingsTabs from '@/Components/SettingsTabs.vue';

const props = defineProps({
    settings: Object,
});

const form = useForm({ ...props.settings });

const fields = [
    { key: 'business_name', label: 'Business name', help: 'Shown on the app, receipts and statements.' },
    { key: 'tagline', label: 'Tagline', help: 'e.g. Save Daily, Grow Steadily' },
    { key: 'phone', label: 'Business phone', help: 'Printed on receipts and the login screen.' },
    { key: 'address', label: 'Address', help: 'Printed on receipts.' },
    { key: 'customer_id_prefix', label: 'Customer ID prefix', help: 'New customers get IDs like AJ0000126. Letters only.' },
    { key: 'receipt_footer', label: 'Receipt message', help: 'Printed at the bottom of every receipt.' },
];
</script>

<template>
    <AuthenticatedLayout title="Settings">
        <SettingsTabs />

        <form class="card max-w-3xl p-5 lg:p-8" @submit.prevent="form.put(route('settings.update'), { preserveScroll: true })">
            <h2 class="font-semibold text-slate-900">Business details</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div v-for="field in fields" :key="field.key" :class="field.key === 'receipt_footer' || field.key === 'address' ? 'sm:col-span-2' : ''">
                    <label :for="field.key" class="label">{{ field.label }}</label>
                    <input
                        :id="field.key"
                        v-model="form[field.key]"
                        type="text"
                        :class="['input', field.key === 'customer_id_prefix' && 'uppercase']"
                        :maxlength="field.key === 'customer_id_prefix' ? 5 : undefined"
                    />
                    <p class="mt-1.5 text-xs text-slate-500">{{ field.help }}</p>
                    <p v-if="form.errors[field.key]" class="field-error">{{ form.errors[field.key] }}</p>
                </div>
            </div>
            <div class="mt-8 flex justify-end">
                <button type="submit" class="btn-primary" :disabled="form.processing || !form.isDirty">Save Settings</button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
