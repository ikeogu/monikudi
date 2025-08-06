<template>
  <div class="bg-white max-w-md mx-auto p-6 rounded shadow-md print:shadow-none print:p-0 print:max-w-full">
    <!-- Header / Branding -->
    <div class="text-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Grace and Zion Monikud LTD</h1>
      <p class="text-sm text-gray-500">Transaction Receipt</p>
      <div class="border-t border-gray-200 mt-2"></div>
    </div>

    <!-- Transaction Details -->
    <div class="space-y-3 text-gray-700 text-sm leading-relaxed">
      <div class="flex justify-between">
        <span class="font-semibold">Customer:</span>
        <span>{{ customer.full_name }}</span>
      </div>

      <div class="flex justify-between">
        <span class="font-semibold">Customer Reg:</span>
        <span>{{ customer.reg_no }}</span>
      </div>

      <div class="flex justify-between">
        <span class="font-semibold">Transaction Type:</span>
        <span class="capitalize">{{ transaction.type }}</span>
      </div>

      <div class="flex justify-between">
        <span class="font-semibold">Amount:</span>
        <span class="font-bold text-green-600">₦{{ transaction.amount }}</span>
      </div>

      <div class="flex justify-between">
        <span class="font-semibold">Description:</span>
        <span>{{ transaction.description }}</span>
      </div>

      <div class="flex justify-between">
        <span class="font-semibold">Date:</span>
        <span>{{ transaction.date }}</span>
      </div>
    </div>

    <!-- Print Button -->
    <div class="mt-8 text-center print:hidden">
      <button
        @click="handlePrint"
        class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition"
      >
        Print Receipt
      </button>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  customer: Object,
  transaction: Object,
})

function handlePrint() {
  window.print()
}
</script>

<style>
@media print {
  body {
    margin: 0;
    background-color: white;
  }

  .print\:shadow-none {
    box-shadow: none !important;
  }

  .print\:p-0 {
    padding: 0 !important;
  }

  .print\:max-w-full {
    max-width: 100% !important;
  }

  .print\:hidden {
    display: none !important;
  }
}
</style>
