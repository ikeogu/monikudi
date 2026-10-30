<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import Icon from '@/Components/Icon.vue';
import SettingsTabs from '@/Components/SettingsTabs.vue';
import { formatDate, naira } from '@/utils/format';

const props = defineProps({
    staff: Array,
});

const me = computed(() => usePage().props.auth.user.id);
const counts = computed(() => ({
    active: props.staff.filter((member) => member.is_active).length,
    admins: props.staff.filter((member) => member.is_active && member.role === 'admin').length,
}));
</script>

<template>
    <AuthenticatedLayout title="Team" :subtitle="`${counts.active} active · ${counts.admins} admin${counts.admins === 1 ? '' : 's'}`">
        <template #actions>
            <Link :href="route('staff.create')" class="btn-primary">
                <Icon name="user-plus" class="h-5 w-5" /> Add Staff
            </Link>
        </template>

        <SettingsTabs />

        <div class="card divide-y divide-slate-100 overflow-hidden">
            <div v-for="member in staff" :key="member.id" :class="['flex flex-wrap items-center gap-4 px-4 py-4 lg:px-6', !member.is_active && 'bg-slate-50']">
                <Avatar :name="member.name" :size="member.is_active ? 'h-11 w-11 text-sm' : 'h-11 w-11 text-sm opacity-50'" />
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2 font-semibold text-slate-900">
                        {{ member.name }}
                        <span v-if="member.id === me" class="text-xs font-normal text-slate-400">(you)</span>
                        <span :class="['badge', member.role === 'admin' ? 'bg-brand-100 text-brand-800' : 'bg-slate-100 text-slate-700']">
                            {{ member.role === 'admin' ? 'Admin' : 'Collector' }}
                        </span>
                        <span v-if="!member.is_active" class="badge bg-red-100 text-red-700">Deactivated</span>
                    </p>
                    <p class="truncate text-sm text-slate-500">{{ member.email }}<span v-if="member.phone"> · {{ member.phone }}</span></p>
                    <p class="text-xs text-slate-400">
                        {{ member.last_login_at ? `Last login ${formatDate(member.last_login_at)}` : 'Never logged in' }}
                    </p>
                </div>
                <div class="text-right text-sm">
                    <p class="font-semibold text-emerald-600">{{ naira(member.collected_today) }}</p>
                    <p class="text-xs text-slate-500">collected today · {{ member.collections_count.toLocaleString() }} total entries</p>
                </div>
                <Link :href="route('staff.edit', member.id)" class="btn-secondary px-3 py-2">
                    <Icon name="pencil" class="h-4 w-4" /> Edit
                </Link>
            </div>
        </div>

        <div class="card mt-5 p-4 text-sm text-slate-600 lg:p-6">
            <p class="font-semibold text-slate-900">Roles</p>
            <ul class="mt-2 space-y-1.5">
                <li><span class="font-medium text-slate-800">Admin</span>: everything, including staff, settings, backups, and editing or deleting entries.</li>
                <li><span class="font-medium text-slate-800">Collector</span>: registers customers, records deposits and withdrawals, prints receipts, and sees reports for their own collections.</li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
