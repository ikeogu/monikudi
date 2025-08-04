<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-4">Customers</h1>
    <Link href="/customers/create" class="bg-blue-600 text-white px-4 py-2 rounded">Add Customer</Link>
    <table class="w-full mt-4 border">
      <thead>
        <tr class="bg-gray-100 text-left">
          <th class="p-2">Name</th>
          <th class="p-2">Phone</th>
          <th class="p-2">Email</th>
          <th class="p-2">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="customer in customers" :key="customer.id" class="border-t">
          <td class="p-2">{{ customer.name }}</td>
          <td class="p-2">{{ customer.phone }}</td>
          <td class="p-2">{{ customer.email }}</td>
          <td class="p-2 space-x-2">
            <Link :href="`/customers/${customer.id}/edit`" class="text-blue-500">Edit</Link>
            <button @click="destroy(customer.id)" class="text-red-500">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3'
defineProps({ customers: Array })

const destroy = (id) => {
  if (confirm('Are you sure?')) {
    router.delete(`/customers/${id}`)
  }
}
</script>
