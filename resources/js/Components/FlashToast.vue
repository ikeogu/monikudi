<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

const page = usePage();
const message = ref(null);
let timer = null;

watch(
    () => [page.props.flash?.success, page.props.flash?.error],
    ([success, error]) => {
        if (!success && !error) return;
        message.value = error ? { type: 'error', text: error } : { type: 'success', text: success };
        clearTimeout(timer);
        timer = setTimeout(() => (message.value = null), error ? 7000 : 4000);
    },
    { immediate: true },
);
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="-translate-y-2 opacity-0"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="-translate-y-2 opacity-0"
    >
        <div v-if="message" class="fixed inset-x-0 top-3 z-[60] flex justify-center px-4 print:hidden" role="status">
            <div
                :class="[
                    'flex max-w-md items-start gap-3 rounded-xl px-4 py-3 text-sm font-medium shadow-lg ring-1',
                    message.type === 'error' ? 'bg-red-50 text-red-800 ring-red-200' : 'bg-emerald-50 text-emerald-800 ring-emerald-200',
                ]"
            >
                <Icon :name="message.type === 'error' ? 'exclamation-triangle' : 'check-circle'" class="h-5 w-5 shrink-0" />
                <span class="flex-1">{{ message.text }}</span>
                <button type="button" class="opacity-60 hover:opacity-100" aria-label="Dismiss" @click="message = null">
                    <Icon name="x-mark" class="h-4 w-4" />
                </button>
            </div>
        </div>
    </Transition>
</template>
