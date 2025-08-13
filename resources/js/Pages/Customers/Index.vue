<template>
 <div class="p-6">
  <h1 class="text-2xl font-bold mb-4">Customers</h1>
  <Link href="/customers/create" class="bg-blue-600 text-white px-4 py-2 rounded">Add Customer</Link>

  <input
    v-model="filters.search"
    @input="search"
    type="text"
    placeholder="Search customers..."
    class="border px-3 py-2 rounded mt-4 mb-4 w-full md:w-1/2"
  />

  <!-- Responsive Table Wrapper -->
  <div class="overflow-x-auto">
    <table class="min-w-full bg-white border border-gray-200">
      <thead>
        <tr class="bg-gray-100 text-left text-sm md:text-base">
          <th class="p-2 whitespace-nowrap">S/No</th>
          <th class="p-2 whitespace-nowrap">Reg No</th>
          <th class="p-2 whitespace-nowrap">First Name</th>
          <th class="p-2 whitespace-nowrap">Last Name</th>
          <th class="p-2 whitespace-nowrap">Phone</th>
          <th class="p-2 whitespace-nowrap">Bal.(₦)</th>
          <th class="p-2 whitespace-nowrap">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(customer, index) in customers.data" :key="customer.id" class="border-t text-sm md:text-base">
          <td class="p-2">{{ index + 1 }}</td>
          <td class="p-2">{{ customer.reg_no }}</td>
          <td class="p-2">{{ customer.first_name }}</td>
          <td class="p-2">{{ customer.last_name }}</td>
          <td class="p-2">{{ customer.phone }}</td>
          <td class="p-2"> {{ customer.wallet_balance_in_naira }}</td>
        <td class="p-2">
            <div class="flex flex-col sm:flex-row sm:space-x-2 space-y-2 sm:space-y-0">
                <Link
                :href="route('customers.transactions.index', customer.id)"
                class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition text-center"
                >
                Transactions
            </Link>

                <Link
                :href="route('customers.edit', customer.id)"
                class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition text-center"
                >
                Edit
            </Link>

                <button
                @click="destroy(customer.id)"
                class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 transition text-center"
                >
                Delete
                </button>
            </div>
        </td>


        </tr>
      </tbody>
    </table>
    <div class="mt-4" v-if="customers.links.length > 3">
        <nav class="flex flex-wrap gap-2">
            <Link
            v-for="link in customers.links"
            :key="link.label"
            :href="link.url"
            class="px-3 py-1 border rounded"
            :class="{
                'bg-blue-500 text-white': link.active,
                'text-gray-500': !link.url
            }"
            >
            <span v-if="link.label" v-text="link.label"></span>
            </Link>
        </nav>
    </div>
   </div>
</div>

</template>

<script setup>
import { Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineOptions({
  layout: AuthenticatedLayout
});

const props = defineProps({
  customers: Object,
  filters: Object,
})

const filters = ref({ search: props.filters.search || '' })

const search = () => {
  router.get(route('customers.index'), { search: filters.value.search }, {
    preserveState: true,
    replace: true,
  })
}
const destroy = (id) => {
  if (confirm('Are you sure?')) {
    router.delete(`/customers/${id}`)
  }
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    minimumFractionDigits: 0,
  });
};
</script>
