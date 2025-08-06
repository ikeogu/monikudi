<template>
  <div class="p-4 sm:p-6 max-w-xl mx-auto">
    <!-- Flash Messages -->
    <div v-if="$page.props.flash?.success" class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4 text-center">
      {{ $page.props.flash.success }}
    </div>
    <div v-if="$page.props.flash?.error" class="bg-red-100 text-red-800 px-4 py-2 rounded mb-4 text-center">
      {{ $page.props.flash.error }}
    </div>

    <!-- Header -->
    <div class="flex flex-col gap-2 sm:gap-4 mb-6">
      <button @click="goBack" class="text-sm bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded w-fit">
        ← Back
      </button>

      <h2 class="text-xl sm:text-2xl font-bold text-gray-800 text-center">
        ➕ Add Transaction
      </h2>
    </div>

    <!-- Form -->
    <form @submit.prevent="submit" class="space-y-4 bg-white shadow-md rounded-lg p-4 sm:p-6">
      <!-- Customer Search -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Search Customer</label>
        <input
          type="text"
          v-model="search"
          @input="searchCustomers"
          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="Enter name or email"
        />
        <!-- Search Results -->
        <ul v-if="searchResults.length" class="bg-white border border-gray-300 rounded mt-2 max-h-48 overflow-y-auto">
          <li
            v-for="cust in searchResults"
            :key="cust.id"
            @click="selectCustomer(cust)"
            class="p-2 hover:bg-blue-100 cursor-pointer"
          >
            {{ cust.full_name }} ({{ cust.reg_no }})
          </li>
        </ul>
        <div v-if="form.errors.customer_id" class="text-red-500 text-sm mt-1">
          {{ form.errors.customer_id }}
        </div>
      </div>

      <!-- Selected Customer Info -->
      <div v-if="selectedCustomer" class="bg-gray-100 p-3 rounded text-sm">
        <div><strong>Name:</strong> {{ selectedCustomer.full_name }}</div>
        <div><strong>Balance:</strong> NGN {{ selectedCustomer.wallet_balance / 100 }}</div>
      </div>

      <!-- Transaction Type -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
        <select
          v-model="form.type"
          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          required
        >
          <option value="credit">Credit</option>
          <option value="debit">Debit</option>
        </select>
      </div>

      <!-- Amount -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
        <input
          type="number"
          v-model="form.amount"
          class="w-full border border-gray-300 rounded-md px-3 py-2"
          step="0.01"
          min="0"
          required
        />
      </div>

      <!-- Description -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
        <input
          type="text"
          v-model="form.description"
          class="w-full border border-gray-300 rounded-md px-3 py-2"
          required
        />
      </div>

      <!-- Date -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
        <input
          type="date"
          v-model="form.date"
          class="w-full border border-gray-300 rounded-md px-3 py-2"
          required
        />
      </div>

      <!-- Submit -->
      <div class="pt-2">
        <button
          type="submit"
          :disabled="form.processing || !form.customer_id"
          class="w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 disabled:bg-gray-400"
        >
          <span v-if="form.processing">Submitting...</span>
          <span v-else>Submit Transaction</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import axios from 'axios'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineOptions({
  layout: AuthenticatedLayout
});
const search = ref('')
const searchResults = ref([])
const selectedCustomer = ref(null)

const today = new Date().toISOString().split('T')[0]

const form = useForm({
  customer_id: '',
  type: 'credit',
  amount: '',
  description: '',
  date: today
})

function searchCustomers() {
  if (search.value.length < 3) {
    searchResults.value = []
    return
  }

  axios
    .get(route('customers.search'), { params: { query: search.value } })
    .then(response => {
      searchResults.value = response.data
    })
    .catch(err => {
      console.error(err)
      searchResults.value = []
    })
}

function selectCustomer(customer) {
  selectedCustomer.value = customer
  form.customer_id = customer.id
  search.value = customer.first_name
  searchResults.value = []
}

function submit() {
  if (!form.customer_id) {
    alert('Please select a customer before submitting.')
    return
  }

  form.amount = parseFloat(form.amount)

  form.post(route('transactions.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      form.date = today
      selectedCustomer.value = null
      search.value = ''
    },
    onError: err => {
      console.error('Error:', err)
    }
  })
}

function goBack() {
  window.history.back()
}
</script>
