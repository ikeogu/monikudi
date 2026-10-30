<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Icon from '@/Components/Icon.vue';

const business = computed(() => usePage().props.business);
const [firstWord, ...rest] = (usePage().props.business?.business_name || 'Grace Daily Contribution').split(' ');
</script>

<template>
    <div class="flex min-h-screen bg-white">
        <!-- Brand panel -->
        <div class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-brand-800 p-12 text-white lg:flex">
            <div class="absolute -right-24 -top-24 h-96 w-96 rounded-full bg-brand-700/60" />
            <div class="absolute -bottom-32 -left-16 h-96 w-96 rounded-full bg-brand-900/60" />

            <div class="relative flex items-center gap-4" style="--logo-accent: #c0d1ff">
                <ApplicationLogo class="h-16 w-16 text-white" />
                <div class="leading-tight">
                    <p class="text-3xl font-extrabold tracking-wide">{{ firstWord.toUpperCase() }}</p>
                    <p class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-200">{{ rest.join(' ') }}</p>
                </div>
            </div>

            <div class="relative">
                <p class="text-4xl font-bold leading-tight">{{ business.tagline }}</p>
                <div class="mt-10 space-y-5 text-lg">
                    <p v-if="business.phone" class="flex items-center gap-4">
                        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-white text-brand-800"><Icon name="phone" class="h-5 w-5" /></span>
                        {{ business.phone }}
                    </p>
                    <p class="flex items-center gap-4">
                        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-white text-brand-800"><Icon name="shield-check" class="h-5 w-5" /></span>
                        Secure savings, better tomorrow
                    </p>
                </div>
            </div>

            <p class="relative text-sm text-brand-200">&copy; {{ new Date().getFullYear() }} {{ business.business_name }}</p>
        </div>

        <!-- Form -->
        <div class="flex flex-1 flex-col items-center justify-center px-6 py-12">
            <div class="mb-8 flex flex-col items-center text-center lg:hidden">
                <ApplicationLogo class="h-20 w-20 text-brand-800" style="--logo-accent: #3c63ef" />
                <p class="mt-3 text-3xl font-extrabold tracking-wide text-brand-800">{{ firstWord.toUpperCase() }}</p>
                <p class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-800">{{ rest.join(' ') }}</p>
                <p class="mt-2 text-sm text-brand-600">{{ business.tagline }}</p>
            </div>

            <div class="w-full max-w-sm">
                <slot />
            </div>
        </div>
    </div>
</template>
