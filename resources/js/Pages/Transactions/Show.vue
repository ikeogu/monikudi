<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Icon from '@/Components/Icon.vue';
import ThermalSlip from '@/Components/ThermalSlip.vue';
import { entryDateTime, isDeposit, naira, typeLabel } from '@/utils/format';
import { paper, setPaper, SLIP_PAGE_CSS, usePageStyle } from '@/utils/print';
import { receiptText, whatsappUrl } from '@/utils/share';

const props = defineProps({
    transaction: Object,
    paymentMethods: Object,
});

const page = usePage();
const isAdmin = computed(() => page.props.auth.isAdmin);
const business = computed(() => page.props.business);
const deposit = computed(() => isDeposit(props.transaction));
const text = computed(() => receiptText(props.transaction, business.value));
const canShare = typeof navigator !== 'undefined' && Boolean(navigator.share);

// Receipts always print on the thermal roll chosen on this device (58mm or 80mm).
usePageStyle(() => SLIP_PAGE_CSS);

function printReceipt() {
    window.print();
}

function share() {
    navigator.share({ title: `Receipt ${props.transaction.receipt_no}`, text: text.value }).catch(() => {});
}

function destroy() {
    if (confirm('Delete this entry? The customer balance will be adjusted.')) {
        router.delete(route('transactions.destroy', props.transaction.id));
    }
}
</script>

<template>
    <AuthenticatedLayout title="Receipt" :back="route('customers.show', transaction.customer_id)">
        <div class="mx-auto flex max-w-4xl flex-col gap-6 lg:flex-row lg:items-start">
            <!-- The receipt itself: the only thing that prints -->
            <div
                :class="[
                    'mx-auto w-full rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 print:w-auto print:max-w-none print:rounded-none print:p-0 print:shadow-none print:ring-0',
                    paper === '58' ? 'max-w-[19rem]' : 'max-w-sm',
                ]"
            >
                <ThermalSlip :paper="paper" heading="RECEIPT" :reference="transaction.receipt_no">
                    <dl class="mt-4 space-y-1.5 border-y border-dashed border-slate-300 py-3 print:mt-2 print:space-y-0.5 print:border-black print:py-2">
                        <div class="flex justify-between gap-3"><dt class="text-slate-500 print:text-black">Date</dt><dd class="text-right">{{ entryDateTime(transaction) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500 print:text-black">Customer ID</dt><dd class="text-right">{{ transaction.customer.reg_no }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500 print:text-black">Customer</dt><dd class="text-right">{{ transaction.customer.full_name }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500 print:text-black">Type</dt><dd class="text-right">{{ typeLabel(transaction.type) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500 print:text-black">Method</dt><dd class="text-right">{{ paymentMethods[transaction.payment_method] || transaction.payment_method }}</dd></div>
                        <div v-if="transaction.description" class="flex justify-between gap-3"><dt class="text-slate-500 print:text-black">Remarks</dt><dd class="text-right">{{ transaction.description }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500 print:text-black">{{ deposit ? 'Collected by' : 'Paid by' }}</dt><dd class="text-right">{{ transaction.collector?.name || '—' }}</dd></div>
                    </dl>

                    <div class="space-y-1 py-3 print:py-2">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="font-semibold">Amount</span>
                            <span :class="['text-[1.45em] font-extrabold print:text-black', deposit ? 'text-emerald-600' : 'text-red-600']">{{ naira(transaction.amount) }}</span>
                        </div>
                        <div v-if="transaction.balance_after !== null" class="flex justify-between gap-2">
                            <span class="text-slate-500 print:text-black">Balance</span>
                            <span class="font-semibold">{{ naira(transaction.balance_after) }}</span>
                        </div>
                    </div>
                </ThermalSlip>
            </div>

            <!-- Actions -->
            <div class="space-y-3 lg:w-72 print:hidden">
                <a :href="whatsappUrl(transaction.customer.phone, text)" target="_blank" rel="noopener" class="btn-whatsapp w-full py-3">
                    <Icon name="whatsapp" class="h-5 w-5" /> Send on WhatsApp
                </a>
                <button type="button" class="btn-primary w-full py-3" @click="printReceipt">
                    <Icon name="printer" class="h-5 w-5" /> Print Receipt
                </button>
                <div class="flex items-center justify-center gap-2 text-xs text-slate-500">
                    Paper
                    <button
                        v-for="size in ['58', '80']"
                        :key="size"
                        type="button"
                        :class="['rounded-full px-2.5 py-0.5 font-medium ring-1', paper === size ? 'bg-brand-800 text-white ring-brand-800' : 'ring-slate-300']"
                        @click="setPaper(size)"
                    >
                        {{ size }}mm
                    </button>
                </div>
                <button v-if="canShare" type="button" class="btn-secondary w-full" @click="share">
                    <Icon name="share" class="h-5 w-5" /> Share…
                </button>

                <div class="card divide-y divide-slate-100">
                    <Link :href="route('transactions.create', { type: transaction.type })" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        <Icon name="plus" class="h-5 w-5 text-brand-700" /> {{ deposit ? 'Next deposit' : 'Next withdrawal' }}
                    </Link>
                    <Link :href="route('customers.show', transaction.customer_id)" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        <Icon name="document-text" class="h-5 w-5 text-brand-700" /> Customer statement
                    </Link>
                    <template v-if="isAdmin">
                        <Link :href="route('transactions.edit', transaction.id)" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            <Icon name="pencil" class="h-5 w-5 text-slate-500" /> Edit entry
                        </Link>
                        <button type="button" class="flex w-full items-center gap-3 px-4 py-3 text-sm font-medium text-red-600 hover:bg-red-50" @click="destroy">
                            <Icon name="trash" class="h-5 w-5" /> Delete entry
                        </button>
                    </template>
                </div>

                <p v-if="transaction.creator" class="text-center text-xs text-slate-400">Recorded by {{ transaction.creator.name }}</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
