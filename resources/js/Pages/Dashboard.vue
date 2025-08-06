<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3'
import { onMounted } from 'vue'

const props = defineProps({
    customersCount: Number,
    transactionsCount: Number,
    availableBalance: Number
})

</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 grid gap-6 grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
                  <!-- Quick Links -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-700">Quick Actions</h3>
                    <div class="mt-4 space-y-2">
                        <Link :href="route('customers.create')" class="block text-sm text-indigo-600 hover:underline">➕ Add Customer</Link>
                         <Link :href="route('quickTransaction')" class="block text-sm text-indigo-600 hover:underline">➕ Add Transaction</Link>
                    </div>
                </div>

                <!-- Total Customers -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-700">Total Customers</h3>
                    <p class="mt-2 text-3xl font-bold text-blue-600">{{ customersCount ?? 0}}</p>
                    <Link :href="route('customers.index')" class="text-sm text-blue-500 mt-2 inline-block">Manage Customers</Link>
                </div>

                <!-- Total Transactions -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-700">Total Transactions</h3>
                    <p class="mt-2 text-3xl font-bold text-green-600">{{ transactionsCount ?? 0}}</p>
                    <Link :href="route('allTransactionz')" class="text-sm text-green-500 mt-2 inline-block">View All Transactions</Link>
                </div>

                 <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-700">Total Balance</h3>
                    <p class="mt-2 text-3xl font-bold text-green-600">{{ availableBalance ?? 0}}</p>

                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
