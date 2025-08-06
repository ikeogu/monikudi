<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-4">Edit Customer</h1>
    <form @submit.prevent="submit" class="space-y-4 max-w-md">
      <div>
        <label class="block">First Name</label>
        <input v-model="form.first_name" type="text" class="input" />
        <div v-if="form.errors.first_name" class="text-red-500 text-sm">{{ form.errors.first_name }}</div>
      </div>
       <div>
        <label class="block">Last Name</label>
        <input v-model="form.last_name" type="text" class="input" />
        <div v-if="form.errors.last_name" class="text-red-500 text-sm">{{ form.errors.last_name }}</div>
      </div>
      <div>
        <label class="block">Phone</label>
        <input v-model="form.phone" type="text" class="input" />
        <div v-if="form.errors.phone" class="text-red-500 text-sm">{{ form.errors.phone }}</div>
      </div>
      <div>
        <label class="block">Reg No</label>
        <input v-model="form.reg_no" type="text" class="input" />
        <div v-if="form.errors.reg_no" class="text-red-500 text-sm">{{ form.errors.reg_no }}</div>
      </div>
      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Update</button>
    </form>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineOptions({
  layout: AuthenticatedLayout
});

const props = defineProps({ customer: Object })

const form = useForm({
    first_name: props.customer.first_name,
    last_name: props.customer.last_name,
    phone: props.customer.phone,
    reg_no: props.customer.reg_no
})

const submit = () => {
  form.put(`/customers/${props.customer.id}`)
}
</script>
