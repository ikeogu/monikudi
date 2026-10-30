<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Icon from '@/Components/Icon.vue';
import { entryDateTime, isDeposit, naira, typeLabel } from '@/utils/format';
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

// Thermal printers come in 58mm and 80mm rolls; remember the choice per device.
const paper = ref(readPaper());
let pageStyle = null;

function readPaper() {
    try {
        return localStorage.getItem('receipt-paper') || '80';
    } catch {
        return '80';
    }
}

function applyPageSize() {
    pageStyle.textContent = `@media print { @page { size: ${paper.value}mm auto; margin: 3mm; } }`;
}

onMounted(() => {
    pageStyle = document.createElement('style');
    document.head.appendChild(pageStyle);
    applyPageSize();
});
onBeforeUnmount(() => pageStyle?.remove());

watch(paper, (value) => {
    try {
        localStorage.setItem('receipt-paper', value);
    } catch {}
    applyPageSize();
});

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
                :class="['receipt mx-auto w-full rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 print:rounded-none print:p-0 print:shadow-none print:ring-0', paper === '58' ? 'max-w-[58mm] text-[11px]' : 'max-w-sm text-sm']"
            >
                <div class="flex flex-col items-center text-center">
                    <ApplicationLogo class="h-12 w-12 text-brand-800 print:text-black" style="--logo-accent: #3c63ef" />
                    <p class="mt-2 font-extrabold uppercase tracking-wide text-brand-800 print:text-black">{{ business.business_name }}</p>
                    <p v-if="business.address" class="text-xs text-slate-500 print:text-black">{{ business.address }}</p>
                    <p v-if="business.phone" class="text-xs text-slate-500 print:text-black">{{ business.phone }}</p>
                    <p class="mt-3 font-bold tracking-[0.2em]">RECEIPT</p>
                    <p class="text-xs text-slate-500 print:text-black">{{ transaction.receipt_no }}</p>
                </div>

                <dl class="mt-4 space-y-1.5 border-y border-dashed border-slate-300 py-3 print:border-black">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 print:text-black">Date</dt><dd class="text-right">{{ entryDateTime(transaction) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 print:text-black">Customer ID</dt><dd class="text-right">{{ transaction.customer.reg_no }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 print:text-black">Customer</dt><dd class="text-right">{{ transaction.customer.full_name }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 print:text-black">Type</dt><dd class="text-right">{{ typeLabel(transaction.type) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 print:text-black">Method</dt><dd class="text-right">{{ paymentMethods[transaction.payment_method] || transaction.payment_method }}</dd></div>
                    <div v-if="transaction.description" class="flex justify-between gap-3"><dt class="text-slate-500 print:text-black">Remarks</dt><dd class="text-right">{{ transaction.description }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 print:text-black">{{ deposit ? 'Collected by' : 'Paid by' }}</dt><dd class="text-right">{{ transaction.collector?.name || '—' }}</dd></div>
                </dl>

                <div class="space-y-1 py-3">
                    <div class="flex items-baseline justify-between">
                        <span class="font-semibold">Amount</span>
                        <span :class="['text-xl font-extrabold print:text-black', deposit ? 'text-emerald-600' : 'text-red-600']">{{ naira(transaction.amount) }}</span>
                    </div>
                    <div v-if="transaction.balance_after !== null" class="flex justify-between">
                        <span class="text-slate-500 print:text-black">Balance</span>
                        <span class="font-semibold">{{ naira(transaction.balance_after) }}</span>
                    </div>
                </div>

                <div class="border-t border-dashed border-slate-300 pt-3 text-center text-xs print:border-black">
                    <p class="font-medium">{{ business.receipt_footer }}</p>
                    <p class="text-slate-500 print:text-black">{{ business.tagline }}</p>
                </div>
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
                        @click="paper = size"
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
