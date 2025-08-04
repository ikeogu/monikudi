<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-4">Edit Customer</h1>
    <form @submit.prevent="submit" class="space-y-4 max-w-md">
      <div>
        <label class="block">Name</label>
        <input v-model="form.name" type="text" class="input" />
        <div v-if="form.errors.name" class="text-red-500 text-sm">{{ form.errors.name }}</div>
      </div>
      <div>
        <label class="block">Phone</label>
        <input v-model="form.phone" type="text" class="input" />
        <div v-if="form.errors.phone" class="text-red-500 text-sm">{{ form.errors.phone }}</div>
      </div>
      <div>
        <label class="block">Email</label>
        <input v-model="form.email" type="email" class="input" />
        <div v-if="form.errors.email" class="text-red-500 text-sm">{{ form.errors.email }}</div>
      </div>
      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Update</button>
    </form>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
defineProps({ customer: Object })

const form = useForm({
  name: customer.name,
  phone: customer.phone,
  email: customer.email
})

const submit = () => {
  form.put(`/customers/${customer.id}`)
}
</script>
