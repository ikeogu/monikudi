<template>
  <div class="p-4 sm:p-6 max-w-xl mx-auto">
    <!-- ✅ Flash Success Message -->
    <div v-if="$page.props.flash?.success" class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4 text-center">
      {{ $page.props.flash.success }}
    </div>

    <!-- ❌ Flash Error Message -->
    <div v-if="$page.props.flash?.error" class="bg-red-100 text-red-800 px-4 py-2 rounded mb-4 text-center">
      {{ $page.props.flash.error }}
    </div>
    <div class="p-6">

    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <button @click="goBack()" class="text-sm bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">
        ← Back
        </button>

        <h2 class="text-xl sm:text-2xl font-bold text-gray-800 text-center lg:text-left">
        ➕ Add Transaction for {{ customer.full_name }}
        </h2>

        <strong class="text-lg sm:text-xl lg:text-2xl text-green-700 text-center lg:text-right">
        Current Bal. NGN {{ customer.wallet_balance_in_naira }}
        </strong>

    </div>
    </div>


    <form @submit.prevent="submit" class="space-y-4 bg-white shadow-md rounded-lg p-4 sm:p-6">
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
        <div v-if="form.errors.type" class="text-red-500 text-sm mt-1">
          {{ form.errors.type }}
        </div>
      </div>

      <!-- Amount -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
        <input
          type="number"
          v-model="form.amount"
          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          step="0.01"
          min="0"
          required
        />
        <div v-if="form.errors.amount" class="text-red-500 text-sm mt-1">
          {{ form.errors.amount }}
        </div>
      </div>

      <!-- Description -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
        <input
          type="text"
          v-model="form.description"
          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          required
        />
        <div v-if="form.errors.description" class="text-red-500 text-sm mt-1">
          {{ form.errors.description }}
        </div>
      </div>

      <!-- Date -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
        <input
          type="date"
          v-model="form.date"
          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          required
        />
        <div v-if="form.errors.date" class="text-red-500 text-sm mt-1">
          {{ form.errors.date }}
        </div>
      </div>

      <!-- Submit Button -->
      <div class="pt-2">
        <button
          type="submit"
          :disabled="form.processing"
          class="w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 transition duration-300 disabled:bg-gray-400 disabled:cursor-not-allowed"
        >
          <span v-if="form.processing">Submitting...</span>
          <span v-else>Submit Transaction</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import { defineProps } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  customer: {
    type: Object,
    required: true
  }
})

// Initialize form with current date
const today = new Date().toISOString().split('T')[0]

const form = useForm({
  type: 'credit',
  amount: '',
  description: '',
  date: today
})

function submit() {
  // Basic validation before submission
  if (!form.amount || !form.description || !form.date) {
    alert('Please fill in all required fields')
    return
  }

  // Convert amount to number
  form.amount = parseFloat(form.amount)

  form.post(route('customers.transactions.store', props.customer.id), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      form.reset()
      // Reset date to today after reset
      form.date = today
    },
    onError: (errors) => {
      console.error('Form submission errors:', errors)
    }
  })
}

function goBack() {
  window.history.back()
}
</script>
