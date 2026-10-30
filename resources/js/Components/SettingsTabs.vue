<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

const tabs = [
    { label: 'Business', icon: 'cog', route: 'settings.edit', prefix: '/settings' },
    { label: 'Team', icon: 'user-group', route: 'staff.index', prefix: '/staff' },
    { label: 'Backup', icon: 'cloud-arrow-up', route: 'backups.index', prefix: '/backups' },
];

const page = usePage();
</script>

<template>
    <nav class="mb-5 flex gap-1 overflow-x-auto rounded-xl bg-white p-1 ring-1 ring-slate-200 sm:inline-flex">
        <Link
            v-for="tab in tabs"
            :key="tab.label"
            :href="route(tab.route)"
            :class="[
                'flex flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-2 text-sm font-semibold sm:flex-none',
                page.url.startsWith(tab.prefix) ? 'bg-brand-800 text-white' : 'text-slate-600 hover:bg-slate-50',
            ]"
        >
            <Icon :name="tab.icon" class="h-4 w-4" />
            {{ tab.label }}
        </Link>
    </nav>
</template>
