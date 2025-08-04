<template>
  <div>
    <h2 class="text-xl font-bold mb-4">Transactions for {{ customer.name }}</h2>
    <Link :href="route('transactions.create', customer.id)" class="btn btn-primary mb-4">Add Transaction</Link>

    <table class="table-auto w-full text-left">
      <thead>
        <tr>
          <th>Date</th>
          <th>Type</th>
          <th>Amount</th>
          <th>Description</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="transaction in transactions" :key="transaction.id">
          <td>{{ transaction.date }}</td>
          <td>{{ transaction.type }}</td>
          <td>{{ transaction.amount }}</td>
          <td>{{ transaction.description }}</td>
          <td>
            <Link :href="route('transactions.edit', [customer.id, transaction.id])" class="text-blue-600">Edit</Link>
            |
            <button @click="destroy(transaction.id)" class="text-red-600">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3'
import { defineProps } from 'vue'

const props = defineProps({
  customer: Object,
  transactions: Array
})

function destroy(id) {
  if (confirm('Are you sure?')) {
    router.delete(route('transactions.destroy', [props.customer.id, id]))
  }
}
</script>
