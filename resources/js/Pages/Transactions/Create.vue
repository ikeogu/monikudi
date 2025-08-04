<template>
  <div>
    <h2 class="text-xl font-bold mb-4">Add Transaction for {{ customer.name }}</h2>
    <form @submit.prevent="submit">
      <div class="mb-4">
        <label>Type</label>
        <select v-model="form.type" class="input">
          <option value="credit">Credit</option>
          <option value="debit">Debit</option>
        </select>
      </div>
      <div class="mb-4">
        <label>Amount</label>
        <input type="number" v-model="form.amount" class="input" step="0.01">
      </div>
      <div class="mb-4">
        <label>Description</label>
        <input type="text" v-model="form.description" class="input">
      </div>
      <div class="mb-4">
        <label>Date</label>
        <input type="date" v-model="form.date" class="input">
      </div>
      <button class="btn btn-primary">Submit</button>
    </form>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import { defineProps } from 'vue'

const props = defineProps({ customer: Object })

const form = useForm({
  type: 'credit',
  amount: '',
  description: '',
  date: ''
})

function submit() {
  form.post(route('transactions.store', props.customer.id))
}
</script>

