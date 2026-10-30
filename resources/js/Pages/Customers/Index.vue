<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Icon from '@/Components/Icon.vue';
import Pagination from '@/Components/Pagination.vue';
import { formatDate, naira } from '@/utils/format';

const props = defineProps({
    customers: Object,
    filters: Object,
});

const search = ref(props.filters.search || '');
let timer = null;

watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('customers.index'), value ? { search: value } : {}, { preserveState: true, replace: true });
    }, 300);
});
</script>

<template>
    <AuthenticatedLayout title="Customers" :subtitle="`${customers.total.toLocaleString()} registered`">
        <div class="mb-4 flex gap-2">
            <div class="relative flex-1 lg:max-w-md">
                <Icon name="magnifying-glass" class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                <input v-model="search" type="search" class="input pl-11" placeholder="Search name, phone or customer ID" />
            </div>
            <Link :href="route('customers.create')" class="btn-primary px-3 lg:px-4" aria-label="Register customer">
                <Icon name="plus" class="h-5 w-5" />
                <span class="hidden sm:inline">Register Customer</span>
            </Link>
        </div>

        <div class="card overflow-hidden">
            <template v-if="customers.data.length">
                <!-- Phones: compact list like the design -->
                <div class="divide-y divide-slate-100 md:hidden">
                    <Link
                        v-for="customer in customers.data"
                        :key="customer.id"
                        :href="route('customers.show', customer.id)"
                        class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50"
                    >
                        <Avatar :name="customer.full_name" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ customer.reg_no }}</p>
                            <p class="truncate text-sm text-slate-700">{{ customer.full_name }}</p>
                            <p class="truncate text-xs text-slate-400">{{ customer.phone }}</p>
                        </div>
                        <p class="text-sm font-semibold text-emerald-600">{{ naira(customer.balance) }}</p>
                    </Link>
                </div>

                <!-- Tablets and up -->
                <table class="hidden min-w-full text-sm md:table">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Customer</th>
                            <th class="px-4 py-3">Customer ID</th>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3">Registered</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                            <th class="px-6 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="customer in customers.data" :key="customer.id" class="hover:bg-slate-50">
                            <td class="px-6 py-3">
                                <Link :href="route('customers.show', customer.id)" class="flex items-center gap-3">
                                    <Avatar :name="customer.full_name" size="h-9 w-9 text-xs" />
                                    <span class="font-medium text-slate-900">{{ customer.full_name }}</span>
                                </Link>
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-700">{{ customer.reg_no }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ customer.phone || '—' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ formatDate(customer.created_at) }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-emerald-600">{{ naira(customer.balance) }}</td>
                            <td class="px-6 py-3">
                                <div class="flex justify-end gap-1">
                                    <Link :href="route('transactions.create', { type: 'credit', customer: customer.id })" class="rounded-lg p-2 text-emerald-600 hover:bg-emerald-50" title="New deposit">
                                        <Icon name="arrow-down-circle" class="h-5 w-5" />
                                    </Link>
                                    <Link :href="route('customers.show', customer.id)" class="rounded-lg p-2 text-brand-700 hover:bg-brand-50" title="Statement">
                                        <Icon name="document-text" class="h-5 w-5" />
                                    </Link>
                                    <Link :href="route('customers.edit', customer.id)" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="Edit">
                                        <Icon name="pencil" class="h-5 w-5" />
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </template>

            <EmptyState
                v-else
                icon="users"
                :title="filters.search ? 'No matching customers' : 'No customers yet'"
                :text="filters.search ? `Nothing matches “${filters.search}”. Try a name, phone number or customer ID.` : 'Register your first customer to start recording contributions.'"
            >
                <Link :href="route('customers.create')" class="btn-primary"><Icon name="user-plus" class="h-4 w-4" /> Register Customer</Link>
            </EmptyState>
        </div>

        <div class="mt-4">
            <Pagination :paginator="customers" />
        </div>
    </AuthenticatedLayout>
</template>
