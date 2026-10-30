<script setup>
defineProps({
    form: { type: Object, required: true },
    roles: { type: Array, required: true },
    editing: { type: Boolean, default: false },
});
</script>

<template>
    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="name" class="label">Full name</label>
            <input id="name" v-model="form.name" type="text" class="input" required />
            <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
        </div>
        <div>
            <label for="phone" class="label">Phone <span class="font-normal text-slate-400">(optional)</span></label>
            <input id="phone" v-model="form.phone" type="tel" class="input" />
            <p v-if="form.errors.phone" class="field-error">{{ form.errors.phone }}</p>
        </div>
        <div class="sm:col-span-2">
            <label for="email" class="label">Email (used to log in)</label>
            <input id="email" v-model="form.email" type="email" class="input" required autocomplete="off" />
            <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
        </div>

        <fieldset class="sm:col-span-2">
            <legend class="label">Role</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                <label
                    v-for="role in roles"
                    :key="role"
                    :class="['flex cursor-pointer gap-3 rounded-xl p-4 ring-1', form.role === role ? 'bg-brand-50 ring-2 ring-brand-600' : 'ring-slate-200 hover:bg-slate-50']"
                >
                    <input v-model="form.role" type="radio" :value="role" class="mt-0.5 text-brand-700 focus:ring-brand-500" />
                    <span>
                        <span class="block text-sm font-semibold capitalize text-slate-900">{{ role }}</span>
                        <span class="block text-xs text-slate-500">
                            {{ role === 'admin' ? 'Full access, including team, settings and backups.' : 'Records collections and sees their own reports.' }}
                        </span>
                    </span>
                </label>
            </div>
            <p v-if="form.errors.role" class="field-error">{{ form.errors.role }}</p>
        </fieldset>

        <div>
            <label for="password" class="label">{{ editing ? 'New password' : 'Password' }}</label>
            <input id="password" v-model="form.password" type="password" class="input" :required="!editing" autocomplete="new-password" />
            <p v-if="editing" class="mt-1.5 text-xs text-slate-500">Leave blank to keep the current password.</p>
            <p v-if="form.errors.password" class="field-error">{{ form.errors.password }}</p>
        </div>
        <div>
            <label for="password_confirmation" class="label">Confirm password</label>
            <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="input" :required="!editing && !!form.password" autocomplete="new-password" />
        </div>

        <label class="flex items-start gap-3 rounded-xl bg-slate-50 p-4 sm:col-span-2">
            <input v-model="form.is_active" type="checkbox" class="mt-0.5 rounded border-slate-300 text-brand-700 focus:ring-brand-500" />
            <span>
                <span class="block text-sm font-semibold text-slate-900">Account active</span>
                <span class="block text-xs text-slate-500">Deactivated staff are signed out immediately and can't log in. Their past entries are kept.</span>
            </span>
        </label>
    </div>
</template>
