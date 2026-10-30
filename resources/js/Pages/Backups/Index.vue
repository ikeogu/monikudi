<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Icon from '@/Components/Icon.vue';
import SettingsTabs from '@/Components/SettingsTabs.vue';
import { fileSize, formatDate, formatTime } from '@/utils/format';

defineProps({
    backups: Array,
    disk: String,
    keep: Number,
    lastBackupAt: String,
});

const form = useForm({});
</script>

<template>
    <AuthenticatedLayout title="Backup">
        <SettingsTabs />

        <div class="grid gap-5 lg:grid-cols-3">
            <div class="card p-5 lg:p-6">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-700">
                    <Icon name="cloud-arrow-up" class="h-6 w-6" />
                </span>
                <h2 class="mt-4 font-semibold text-slate-900">Automatic daily backup</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Staff, customers, transactions and settings are backed up every day and the latest {{ keep }} copies are kept.
                </p>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Last backup</dt>
                        <dd class="font-medium text-slate-900">{{ lastBackupAt ? `${formatDate(lastBackupAt)}, ${formatTime(lastBackupAt)}` : 'Never' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Stored on</dt>
                        <dd class="font-medium text-slate-900">{{ disk === 's3' ? 'Cloud storage' : 'App server' }}</dd>
                    </div>
                </dl>
                <p v-if="disk !== 's3'" class="mt-4 rounded-xl bg-amber-50 p-3 text-xs text-amber-900">
                    Backups are on the app server. Set <code>BACKUP_DISK=s3</code> with cloud storage credentials to keep them safe if the server is lost.
                </p>
                <button type="button" class="btn-primary mt-5 w-full" :disabled="form.processing" @click="form.post(route('backups.store'), { preserveScroll: true })">
                    <Icon name="arrow-path" :class="['h-5 w-5', form.processing && 'animate-spin']" />
                    {{ form.processing ? 'Backing up…' : 'Back up now' }}
                </button>
            </div>

            <div class="card overflow-hidden lg:col-span-2">
                <h2 class="px-5 pt-5 font-semibold text-slate-900 lg:px-6">Backups</h2>
                <ul v-if="backups.length" class="mt-3 divide-y divide-slate-100">
                    <li v-for="backup in backups" :key="backup.name" class="flex items-center gap-4 px-5 py-3 lg:px-6">
                        <Icon name="document-text" class="h-5 w-5 text-slate-400" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-900">{{ formatDate(backup.created_at) }}, {{ formatTime(backup.created_at) }}</p>
                            <p class="truncate text-xs text-slate-500">{{ backup.name }} · {{ fileSize(backup.size) }}</p>
                        </div>
                        <a :href="route('backups.download', backup.name)" class="btn-secondary px-3 py-1.5">
                            <Icon name="arrow-down-tray" class="h-4 w-4" /> Download
                        </a>
                    </li>
                </ul>
                <EmptyState v-else icon="cloud-arrow-up" title="No backups yet" text="Run your first backup now." />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
