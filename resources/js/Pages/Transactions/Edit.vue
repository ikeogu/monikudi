
<template>
  <div>
    <h2 class="text-xl font-bold mb-4">Edit Transaction</h2>
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
      <button class="btn btn-primary">Update</button>
    </form>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import { defineProps } from 'vue'

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
  form.put(route('transactions.update', [props.customer.id, props.transaction.id]))
}
</script>
