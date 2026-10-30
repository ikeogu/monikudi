<script setup>
import { ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import Avatar from '@/Components/Avatar.vue';
import Icon from '@/Components/Icon.vue';
import { naira } from '@/utils/format';

const props = defineProps({
    modelValue: { type: Object, default: null },
    locked: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);

const query = ref('');
const results = ref([]);
const loading = ref(false);
const open = ref(false);
const highlighted = ref(0);
const input = ref(null);
let timer = null;
let latestRequest = 0;

watch(query, (value) => {
    clearTimeout(timer);
    if (value.trim().length < 2) {
        results.value = [];
        loading.value = false;
        return;
    }
    loading.value = true;
    timer = setTimeout(() => search(value.trim()), 250);
});

async function search(term) {
    const request = ++latestRequest;
    try {
        const { data } = await axios.get(route('customers.search'), { params: { query: term } });
        if (request === latestRequest) {
            results.value = data;
            highlighted.value = 0;
            open.value = true;
        }
    } catch {
        if (request === latestRequest) results.value = [];
    } finally {
        if (request === latestRequest) loading.value = false;
    }
}

function select(customer) {
    emit('update:modelValue', customer);
    query.value = '';
    results.value = [];
    open.value = false;
}

function clear() {
    emit('update:modelValue', null);
    setTimeout(() => input.value?.focus());
}

// Let a click on a result land before the list disappears.
function closeSoon() {
    setTimeout(() => (open.value = false), 150);
}

function move(step) {
    if (!results.value.length) return;
    highlighted.value = (highlighted.value + step + results.value.length) % results.value.length;
}

function chooseHighlighted() {
    if (results.value[highlighted.value]) select(results.value[highlighted.value]);
}
</script>

<template>
    <div class="relative">
        <div v-if="modelValue" class="flex items-center gap-3 rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 shadow-sm">
            <Avatar :name="modelValue.full_name" size="h-9 w-9 text-xs" />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-slate-900">{{ modelValue.reg_no }} - {{ modelValue.full_name }}</p>
                <p class="truncate text-xs text-slate-500">
                    {{ modelValue.phone || 'No phone' }} · Balance <span class="font-medium text-emerald-600">{{ naira(modelValue.balance) }}</span>
                </p>
            </div>
            <button v-if="!locked" type="button" class="text-sm font-medium text-brand-700 hover:text-brand-900" @click="clear">Change</button>
        </div>

        <template v-else>
            <div class="relative">
                <input
                    ref="input"
                    v-model="query"
                    type="search"
                    class="input pr-10"
                    placeholder="Search by name, phone or customer ID"
                    autocomplete="off"
                    role="combobox"
                    :aria-expanded="open"
                    @focus="open = results.length > 0"
                    @blur="closeSoon"
                    @keydown.down.prevent="move(1)"
                    @keydown.up.prevent="move(-1)"
                    @keydown.enter.prevent="chooseHighlighted"
                    @keydown.esc="open = false"
                />
                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                    <svg v-if="loading" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" class="opacity-25" />
                        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                    </svg>
                    <Icon v-else name="user-circle" class="h-5 w-5" />
                </span>
            </div>

            <ul
                v-if="open && query.trim().length >= 2 && !loading"
                class="absolute z-30 mt-1.5 max-h-72 w-full overflow-y-auto rounded-xl bg-white py-1 shadow-lg ring-1 ring-slate-200"
                role="listbox"
            >
                <li
                    v-for="(customer, index) in results"
                    :key="customer.id"
                    role="option"
                    :aria-selected="index === highlighted"
                    :class="['flex cursor-pointer items-center gap-3 px-3 py-2.5', index === highlighted ? 'bg-brand-50' : 'hover:bg-slate-50']"
                    @mousedown.prevent="select(customer)"
                    @mouseenter="highlighted = index"
                >
                    <Avatar :name="customer.full_name" size="h-8 w-8 text-xs" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-900">{{ customer.full_name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ customer.reg_no }} · {{ customer.phone || 'No phone' }}</p>
                    </div>
                    <span class="text-sm font-semibold text-emerald-600">{{ naira(customer.balance) }}</span>
                </li>
                <li v-if="!results.length" class="px-3 py-3 text-sm text-slate-500">
                    No customer matches "{{ query }}".
                    <Link :href="route('customers.create')" class="font-medium text-brand-700">Register a new customer</Link>
                </li>
            </ul>
        </template>
    </div>
</template>
