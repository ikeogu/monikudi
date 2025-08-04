<template>
  <div>
    <h2 class="text-xl font-bold mb-4">All Transactions</h2>

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


</script>
