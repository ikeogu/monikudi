<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    // A Laravel paginator serialised by Inertia.
    paginator: { type: Object, required: true },
});
</script>

<template>
    <div v-if="paginator.last_page > 1" class="flex flex-col items-center justify-between gap-3 text-sm sm:flex-row">
        <p class="text-slate-500">
            Showing {{ paginator.from }}–{{ paginator.to }} of {{ paginator.total }}
        </p>
        <nav class="flex flex-wrap gap-1">
            <template v-for="link in paginator.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    :class="[
                        'min-w-9 rounded-lg px-3 py-1.5 text-center font-medium',
                        link.active ? 'bg-brand-800 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50',
                    ]"
                    v-html="link.label"
                />
                <span v-else class="min-w-9 rounded-lg px-3 py-1.5 text-center text-slate-300" v-html="link.label" />
            </template>
        </nav>
    </div>
</template>
