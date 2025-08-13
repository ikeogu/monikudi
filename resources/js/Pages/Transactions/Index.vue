<template>
  <div class="p-4 sm:p-6">
    <button @click="goBack()" class="text-sm bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">
    ← Back
    </button>

   <div class="p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">

            <h2 class="text-xl sm:text-2xl font-bold text-gray-800 text-center lg:text-left">
            ➕ Add Transaction for {{ customer.full_name }}
            </h2>

            <strong class="text-lg sm:text-xl lg:text-2xl text-green-700 text-center lg:text-right">
            Current Bal. NGN {{ customer.wallet_balance_in_naira }}
            </strong>

        </div>
    </div>

    <Link
      :href="route('customers.transactions.create', customer.id)"
      class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition block sm:inline-block mb-4"
    >
      ➕ Add Transaction
    </Link>

    <form @submit.prevent="searchTransactions" class="mb-4">
    <input
            v-model="search"
            type="text"
            placeholder="Search..."
            class="w-full sm:w-64 px-3 py-2 border rounded"
        />
    </form>
    <div class="overflow-x-auto" v-if="transactions && transactions.data.length">
      <table class="min-w-full divide-y divide-gray-200 border rounded-lg text-sm sm:text-base">
        <thead class="bg-gray-100 text-gray-700">
          <tr>
             <th class="px-4 py-2 whitespace-nowrap">S/No</th>
            <th class="px-4 py-2 whitespace-nowrap">Date</th>
            <th class="px-4 py-2 whitespace-nowrap">Type</th>
            <th class="px-4 py-2 whitespace-nowrap">Amount</th>
            <th class="px-4 py-2 whitespace-nowrap">Description</th>
            <th class="px-4 py-2 whitespace-nowrap">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="(transaction, index) in transactions?.data || []" :key="transaction.id" class="bg-white">
             <td class="px-4 py-2">{{ index + 1 }}</td>
            <td class="text-sm whitespace-nowrap px-2 py-2">{{ transaction.date }}</td>
            <td class="px-4 py-2 capitalize">{{ transaction.type }}</td>
            <td class="px-4 py-2">{{ formatCurrency(transaction.amount) }}</td>
            <td class="px-4 py-2">{{ transaction.description }}</td>
            <td class="px-4 py-2">
            <div class="flex flex-col sm:flex-row gap-2">
                <Link
                :href="route('customers.transactions.edit', [customer.id, transaction.id])"
                class="inline-flex justify-center items-center bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-150 ease-in-out"
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
                @click="destroy(transaction.id)"
                class="inline-flex justify-center items-center bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 transition duration-150 ease-in-out"
                >
                Delete
                </button>
            </div>
            </td>

          </tr>
        </tbody>
      </table>
       <div class="mt-4" v-if="transactions.links.length > 3">
        <nav class="flex flex-wrap gap-2">
            <Link
            v-for="link in transactions.links"
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
    <p v-else class="text-gray-500">No transactions found.</p>

  </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3'
import { defineProps } from 'vue'
import { ref, watch } from 'vue'



const props = defineProps({
  customer: Object,
    transactions: Object,
  filters: Object,
})

const search = ref(props.filters.search || '')

function searchTransactions() {
  router.get(route('customers.transactions.index', props.customer.id), {
    search: search.value
  }, {
    preserveState: true,
    replace: true
  })
}

function destroy(id) {
  if (confirm('Are you sure?')) {
    router.delete(route('transactions.destroy', [props.customer.id, id]))
  }
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    minimumFractionDigits: 0,
  }).format(value);
};

function goBack() {
  window.history.back()
}

</script>
