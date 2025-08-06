<template>
  <div class="p-4">
    <h2 class="text-xl font-bold mb-6 text-gray-800">All Transactions</h2>

    <div class="mb-6">
      <input
        v-model="searchFilter"
        @input="search"
        type="text"
        placeholder="Search by date, type or amount..."
        class="w-full sm:w-80 px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
      />
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full bg-white shadow rounded-md">
        <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
          <tr>
            <th class="px-4 py-3 text-left">S/No</th>
            <th class="px-4 py-3 text-left">Date</th>
            <th class="px-4 py-3 text-left">Type</th>
            <th class="px-4 py-3 text-left">Amount</th>
            <th class="px-4 py-3 text-left">Description</th>
            <th class="px-4 py-3 text-left">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(transaction, index) in transactions.data"
            :key="transaction.id"
            class="border-t hover:bg-gray-50 transition"
          >
            <td class="px-4 py-3">{{ index + 1 }}</td>
            <td class="text-sm whitespace-nowrap px-2 py-2">{{ transaction.date }}</td>
            <td class="px-4 py-3 capitalize">{{ transaction.type }}</td>
            <td class="px-4 py-3 font-semibold text-green-600">{{ transaction.amount }}</td>
            <td class="px-4 py-3">{{ transaction.description }}</td>
            <td class="px-4 py-3">
              <div class="flex flex-col sm:flex-row gap-2">
                <Link
                  :href="route('customers.transactions.edit', [transaction.customer_id, transaction.id])"
                  class="inline-flex justify-center items-center bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition"
                >
                  Edit
                </Link>
                <Link
                    :href="route('transactions.print', [transaction.customer_id, transaction.id])"
                    target="_blank"
                    class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition duration-150 ease-in-out"
                    >
                    Print
                    </Link>
                <button
                  @click="destroy(transaction)"
                  class="inline-flex justify-center items-center bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 transition"
                >
                  Delete
                </button>
              </div>
            </td>
          </tr>

          <tr v-if="transactions.data.length === 0">
            <td colspan="6" class="text-center py-4 text-gray-500">No transactions found.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { router, usePage, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({ layout: AuthenticatedLayout })

const props = defineProps({
  transactions: Object,
  filters: Object,
  customer: Object
})

const searchFilter = ref(props.filters.search || '')

function search() {
  router.get(route('customers.transactions.index', props.customer.id), {
    search: searchFilter.value,
  }, {
    preserveState: true,
    replace: true,
  })
}

function destroy(transaction) {
  if (!transaction || !transaction.id) return;

  if (confirm('Are you sure you want to delete this transaction?')) {
    router.delete(route('customers.transactions.destroy', [transaction.customer_id, transaction.id]))
  }
}
</script>
