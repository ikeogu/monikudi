<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

defineProps({
    paper: { type: String, default: '58' },
    heading: { type: String, required: true },
    reference: { type: String, default: '' },
});

const business = computed(() => usePage().props.business);
</script>

<template>
    <!--
        Thermal heads print narrower than the roll (about 48mm on 58mm paper, 72mm on 80mm),
        so the printed slip is sized to that and anything wider would be cut off.
    -->
    <div
        :class="[
            'mx-auto w-full print:mx-auto print:max-w-none print:text-black',
            paper === '58' ? 'max-w-[16rem] text-[13px] print:w-[48mm] print:text-[11px]' : 'max-w-sm text-sm print:w-[72mm] print:text-[13px]',
        ]"
    >
        <div class="flex flex-col items-center text-center">
            <!-- Solid black in print: thermal heads dither colours into a grey speckle. -->
            <ApplicationLogo class="h-12 w-12 text-brand-800 [--logo-accent:#3c63ef] print:h-9 print:w-9 print:text-black print:[--logo-accent:#000]" />
            <p class="mt-2 font-extrabold uppercase leading-tight tracking-wide text-brand-800 print:mt-1 print:text-black">{{ business.business_name }}</p>
            <p v-if="business.address" class="text-[0.85em] text-slate-500 print:text-black">{{ business.address }}</p>
            <p v-if="business.phone" class="text-[0.85em] text-slate-500 print:text-black">{{ business.phone }}</p>
            <p class="mt-3 font-bold tracking-[0.2em] print:mt-2">{{ heading }}</p>
            <p v-if="reference" class="text-[0.85em] text-slate-500 print:text-black">{{ reference }}</p>
        </div>

        <slot />

        <div class="border-t border-dashed border-slate-300 pt-3 text-center text-[0.85em] print:border-black print:pt-2">
            <p class="font-medium">{{ business.receipt_footer }}</p>
            <p class="text-slate-500 print:text-black">{{ business.tagline }}</p>
        </div>
    </div>
</template>
