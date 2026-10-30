<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Avatar from '@/Components/Avatar.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import FlashToast from '@/Components/FlashToast.vue';
import Icon from '@/Components/Icon.vue';
import { installApp, installPrompt, isOnline } from '@/utils/pwa';

defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    // When set, the mobile header shows a back arrow to this URL instead of the menu button.
    back: { type: String, default: '' },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => page.props.auth.isAdmin);
const business = computed(() => page.props.business);

const drawerOpen = ref(false);
const actionsOpen = ref(false);
const search = ref('');

const path = computed(() => page.url.split('?')[0]);
const query = computed(() => new URLSearchParams(page.url.split('?')[1] || ''));
const transactionType = computed(() => (path.value.startsWith('/transactions') ? query.value.get('type') : null));

const navigation = computed(() => [
    { label: 'Dashboard', icon: 'home', href: route('dashboard'), active: path.value.startsWith('/dashboard') },
    { label: 'Customers', icon: 'users', href: route('customers.index'), active: path.value.startsWith('/customers') },
    { label: 'Deposits', icon: 'arrow-down-circle', href: route('transactions.index', { type: 'credit' }), active: transactionType.value === 'credit' },
    { label: 'Withdrawals', icon: 'arrow-up-circle', href: route('transactions.index', { type: 'debit' }), active: transactionType.value === 'debit' },
    { label: 'Transactions', icon: 'arrows-right-left', href: route('transactions.index'), active: path.value.startsWith('/transactions') && !transactionType.value },
    { label: 'Reports', icon: 'chart-bar', href: route('reports.index'), active: path.value.startsWith('/reports') },
    ...(isAdmin.value
        ? [
              { label: 'Staff', icon: 'user-group', href: route('staff.index'), active: path.value.startsWith('/staff') },
              { label: 'Settings', icon: 'cog', href: route('settings.edit'), active: path.value.startsWith('/settings') },
              { label: 'Backup', icon: 'cloud-arrow-up', href: route('backups.index'), active: path.value.startsWith('/backups') },
          ]
        : []),
]);

const tabs = computed(() => [
    { label: 'Dashboard', icon: 'home', href: route('dashboard'), active: path.value.startsWith('/dashboard') },
    { label: 'Customers', icon: 'users', href: route('customers.index'), active: path.value.startsWith('/customers') },
    null, // the floating "+" button
    { label: 'Transactions', icon: 'arrows-right-left', href: route('transactions.index'), active: path.value.startsWith('/transactions') },
    { label: 'Reports', icon: 'chart-bar', href: route('reports.index'), active: path.value.startsWith('/reports') },
]);

const quickActions = [
    { label: 'New Deposit', icon: 'arrow-down-circle', tone: 'bg-emerald-50 text-emerald-600', href: () => route('transactions.create', { type: 'credit' }) },
    { label: 'New Withdrawal', icon: 'arrow-up-circle', tone: 'bg-red-50 text-red-600', href: () => route('transactions.create', { type: 'debit' }) },
    { label: 'Register Customer', icon: 'user-plus', tone: 'bg-brand-50 text-brand-700', href: () => route('customers.create') },
];

// Close overlays whenever we navigate.
watch(() => page.url, () => {
    drawerOpen.value = false;
    actionsOpen.value = false;
});

function searchCustomers() {
    router.get(route('customers.index'), { search: search.value });
}

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <Head :title="title" />
    <FlashToast />

    <div class="min-h-screen bg-slate-100">
        <!-- Desktop sidebar -->
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-brand-800 text-white lg:flex print:hidden">
            <Link :href="route('dashboard')" class="flex items-center gap-3 px-6 py-6" style="--logo-accent: #c0d1ff">
                <ApplicationLogo class="h-11 w-11 shrink-0 text-white" />
                <div class="leading-tight">
                    <p class="text-lg font-extrabold tracking-wide">{{ business.business_name.split(' ')[0].toUpperCase() }}</p>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-brand-200">
                        {{ business.business_name.split(' ').slice(1).join(' ') }}
                    </p>
                </div>
            </Link>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3">
                <Link
                    v-for="item in navigation"
                    :key="item.label"
                    :href="item.href"
                    :class="[
                        'flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium transition',
                        item.active ? 'bg-brand-600 text-white shadow-sm' : 'text-brand-100 hover:bg-white/10 hover:text-white',
                    ]"
                >
                    <Icon :name="item.icon" class="h-5 w-5" />
                    {{ item.label }}
                </Link>
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium text-brand-100 transition hover:bg-white/10 hover:text-white"
                    @click="logout"
                >
                    <Icon name="logout" class="h-5 w-5" />
                    Logout
                </button>
            </nav>

            <div v-if="installPrompt" class="p-3">
                <button type="button" class="flex w-full items-center gap-3 rounded-xl bg-white/10 px-4 py-3 text-sm font-medium hover:bg-white/15" @click="installApp">
                    <Icon name="device-phone" class="h-5 w-5" />
                    Install app
                </button>
            </div>
        </aside>

        <!-- Mobile drawer -->
        <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" enter-active-class="transition-opacity" leave-active-class="transition-opacity">
            <div v-if="drawerOpen" class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden" @click="drawerOpen = false" />
        </Transition>
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-brand-800 text-white transition-transform duration-200 lg:hidden print:hidden',
                drawerOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
            style="padding-top: env(safe-area-inset-top)"
        >
            <div class="flex items-center justify-between px-5 py-5">
                <div class="flex items-center gap-3" style="--logo-accent: #c0d1ff">
                    <ApplicationLogo class="h-10 w-10 text-white" />
                    <p class="text-sm font-bold leading-tight">{{ business.business_name }}</p>
                </div>
                <button type="button" class="rounded-lg p-1.5 text-brand-100 hover:bg-white/10" aria-label="Close menu" @click="drawerOpen = false">
                    <Icon name="x-mark" class="h-6 w-6" />
                </button>
            </div>
            <div class="mx-3 mb-3 flex items-center gap-3 rounded-xl bg-white/10 px-3 py-3">
                <Avatar :name="user.name" size="h-10 w-10 text-sm" />
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold">{{ user.name }}</p>
                    <p class="text-xs capitalize text-brand-200">{{ user.role }}</p>
                </div>
            </div>
            <nav class="flex-1 space-y-1 overflow-y-auto px-3">
                <Link
                    v-for="item in navigation"
                    :key="item.label"
                    :href="item.href"
                    :class="['flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium', item.active ? 'bg-brand-600' : 'text-brand-100 hover:bg-white/10']"
                >
                    <Icon :name="item.icon" class="h-5 w-5" />
                    {{ item.label }}
                </Link>
                <Link :href="route('profile.edit')" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium text-brand-100 hover:bg-white/10">
                    <Icon name="user-circle" class="h-5 w-5" />
                    My Profile
                </Link>
                <button v-if="installPrompt" type="button" class="flex w-full items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium text-brand-100 hover:bg-white/10" @click="installApp">
                    <Icon name="device-phone" class="h-5 w-5" />
                    Install app
                </button>
                <button type="button" class="flex w-full items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium text-brand-100 hover:bg-white/10" @click="logout">
                    <Icon name="logout" class="h-5 w-5" />
                    Logout
                </button>
            </nav>
        </aside>

        <div class="lg:pl-64">
            <!-- Mobile header -->
            <header
                class="sticky top-0 z-20 flex items-center gap-2 border-b border-slate-200 bg-white px-2 py-2 lg:hidden print:hidden"
                style="padding-top: max(0.5rem, env(safe-area-inset-top))"
            >
                <Link v-if="back" :href="back" class="rounded-lg p-2 text-slate-700 hover:bg-slate-100" aria-label="Back">
                    <Icon name="chevron-left" class="h-6 w-6" />
                </Link>
                <button v-else type="button" class="rounded-lg p-2 text-slate-700 hover:bg-slate-100" aria-label="Open menu" @click="drawerOpen = true">
                    <Icon name="bars-3" class="h-6 w-6" />
                </button>
                <h1 class="flex-1 truncate text-center text-base font-semibold text-slate-900">{{ title }}</h1>
                <Link :href="route('profile.edit')" class="p-1" aria-label="My profile">
                    <Avatar :name="user.name" size="h-8 w-8 text-xs" />
                </Link>
            </header>

            <!-- Desktop top bar -->
            <header class="sticky top-0 z-20 hidden items-center gap-6 bg-brand-800 px-8 py-3 lg:flex print:hidden">
                <form class="relative w-full max-w-md" @submit.prevent="searchCustomers">
                    <input v-model="search" type="search" placeholder="Search customers..." class="input border-0 pr-10 shadow-none" />
                    <button type="submit" class="absolute inset-y-0 right-3 text-slate-400 hover:text-slate-600" aria-label="Search">
                        <Icon name="magnifying-glass" class="h-5 w-5" />
                    </button>
                </form>
                <div class="ml-auto">
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button type="button" class="flex items-center gap-3 rounded-xl px-2 py-1 text-left text-white hover:bg-white/10">
                                <Avatar :name="user.name" size="h-9 w-9 text-sm" />
                                <span class="leading-tight">
                                    <span class="block text-sm font-semibold">{{ user.name }}</span>
                                    <span class="block text-xs capitalize text-brand-200">{{ user.role === 'admin' ? 'Administrator' : 'Collector' }}</span>
                                </span>
                                <Icon name="chevron-down" class="h-4 w-4 text-brand-200" />
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('profile.edit')">My Profile</DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </header>

            <div v-if="!isOnline" class="flex items-center justify-center gap-2 bg-amber-100 px-4 py-2 text-sm font-medium text-amber-900 print:hidden">
                <Icon name="signal-slash" class="h-4 w-4" />
                You're offline. Entries can't be saved until you reconnect.
            </div>

            <main class="mx-auto max-w-7xl px-4 pb-28 pt-5 sm:px-6 lg:px-8 lg:pb-10 lg:pt-8 print:p-0">
                <div v-if="title || $slots.actions" :class="['mb-5 items-center justify-between gap-3 print:hidden', $slots.actions ? 'flex' : 'hidden lg:flex']">
                    <div class="hidden lg:block">
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ title }}</h1>
                        <p v-if="subtitle" class="mt-0.5 text-sm text-slate-500">{{ subtitle }}</p>
                    </div>
                    <div class="flex w-full flex-wrap items-center justify-end gap-2 lg:w-auto">
                        <slot name="actions" />
                    </div>
                </div>
                <slot />
            </main>
        </div>

        <!-- Mobile bottom tabs -->
        <nav
            class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white lg:hidden print:hidden"
            style="padding-bottom: env(safe-area-inset-bottom)"
        >
            <div class="grid grid-cols-5">
                <template v-for="(tab, index) in tabs" :key="index">
                    <div v-if="!tab" class="flex justify-center">
                        <button
                            type="button"
                            class="-mt-6 flex h-14 w-14 items-center justify-center rounded-full bg-brand-800 text-white shadow-lg ring-4 ring-white transition active:scale-95"
                            aria-label="New entry"
                            @click="actionsOpen = true"
                        >
                            <Icon name="plus" class="h-7 w-7" />
                        </button>
                    </div>
                    <Link
                        v-else
                        :href="tab.href"
                        :class="['flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium', tab.active ? 'text-brand-800' : 'text-slate-500']"
                    >
                        <Icon :name="tab.icon" class="h-6 w-6" />
                        {{ tab.label }}
                    </Link>
                </template>
            </div>
        </nav>

        <!-- Quick action sheet -->
        <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" enter-active-class="transition-opacity" leave-active-class="transition-opacity">
            <div v-if="actionsOpen" class="fixed inset-0 z-40 bg-slate-900/50" @click="actionsOpen = false" />
        </Transition>
        <Transition enter-from-class="translate-y-full" leave-to-class="translate-y-full" enter-active-class="transition-transform" leave-active-class="transition-transform">
            <div
                v-if="actionsOpen"
                class="fixed inset-x-0 bottom-0 z-50 rounded-t-3xl bg-white px-4 pt-3 shadow-2xl"
                style="padding-bottom: max(1.25rem, env(safe-area-inset-bottom))"
            >
                <div class="mx-auto mb-4 h-1.5 w-10 rounded-full bg-slate-200" />
                <Link
                    v-for="action in quickActions"
                    :key="action.label"
                    :href="action.href()"
                    class="flex items-center gap-4 rounded-2xl px-3 py-3 hover:bg-slate-50"
                >
                    <span :class="['flex h-11 w-11 items-center justify-center rounded-full', action.tone]">
                        <Icon :name="action.icon" class="h-6 w-6" />
                    </span>
                    <span class="font-semibold text-slate-800">{{ action.label }}</span>
                </Link>
            </div>
        </Transition>
    </div>
</template>
