<template>
  <div class="p-4 sm:p-6 max-w-xl mx-auto bg-white rounded-md shadow">
    <!-- Back Button -->
    <div class="mb-4">
      <button @click="goBack()" class="text-sm bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">
        ← Back
        </button>
    </div>

    <!-- Page Title -->
    <h2 class="text-2xl font-semibold mb-6 text-gray-800">Edit Transaction</h2>

    <!-- Form -->
    <form @submit.prevent="submit" class="space-y-5">
      <!-- Type -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
        <select
          v-model="form.type"
          class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
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
          step="0.01"
          v-model="form.amount"
          class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      </div>

      <!-- Description -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
        <input
          type="text"
          v-model="form.description"
          class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      </div>

      <!-- Date -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
        <input
          type="date"
          v-model="form.date"
          class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      </div>

      <!-- Submit Button -->
      <div class="text-right">
        <button
          type="submit"
          class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition duration-150 ease-in-out"
        >
          Update
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  customer: Object,
  transaction: Object
})

const form = useForm({
  type: props.transaction.type,
  amount: props.transaction.amount,
  description: props.transaction.description,
  date: props.transaction.date
})

function submit() {
  form.put(route('customers.transactions.update', [props.customer.id, props.transaction.id]))
}

function goBack() {
  window.history.back()
}
</script>
