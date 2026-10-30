<script setup>
import { ref } from 'vue';
import axios from 'axios';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    form: { type: Object, required: true },
    suggestedRegNo: { type: String, default: '' },
    canGenerate: { type: Boolean, default: true },
});

const generating = ref(false);

async function generateRegNo() {
    generating.value = true;
    try {
        const { data } = await axios.get(route('customers.next-id'));
        props.form.reg_no = data.reg_no;
        props.form.clearErrors('reg_no');
    } catch {
        props.form.setError('reg_no', "Couldn't generate an ID. Check your connection and try again.");
    } finally {
        generating.value = false;
    }
}
</script>

<template>
    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="first_name" class="label">First name</label>
            <input id="first_name" v-model="form.first_name" type="text" class="input" required autocomplete="off" />
            <p v-if="form.errors.first_name" class="field-error">{{ form.errors.first_name }}</p>
        </div>
        <div>
            <label for="last_name" class="label">Last name</label>
            <input id="last_name" v-model="form.last_name" type="text" class="input" required autocomplete="off" />
            <p v-if="form.errors.last_name" class="field-error">{{ form.errors.last_name }}</p>
        </div>
        <div>
            <label for="phone" class="label">Phone number</label>
            <input id="phone" v-model="form.phone" type="tel" inputmode="tel" class="input" placeholder="08012345678" />
            <p class="mt-1.5 text-xs text-slate-500">Used to send receipts on WhatsApp.</p>
            <p v-if="form.errors.phone" class="field-error">{{ form.errors.phone }}</p>
        </div>
        <div>
            <label for="reg_no" class="label">Customer ID</label>
            <div class="flex gap-2">
                <input id="reg_no" v-model="form.reg_no" type="text" class="input min-w-0 flex-1 uppercase" :placeholder="suggestedRegNo" />
                <button v-if="canGenerate" type="button" class="btn-secondary shrink-0" :disabled="generating" @click="generateRegNo">
                    <Icon name="arrow-path" :class="['h-4 w-4', { 'animate-spin': generating }]" />
                    Generate
                </button>
            </div>
            <p v-if="suggestedRegNo" class="mt-1.5 text-xs text-slate-500">Tap Generate, or leave blank to use {{ suggestedRegNo }}.</p>
            <p v-if="form.errors.reg_no" class="field-error">{{ form.errors.reg_no }}</p>
        </div>
        <div>
            <label for="email" class="label">Email <span class="font-normal text-slate-400">(optional)</span></label>
            <input id="email" v-model="form.email" type="email" class="input" />
            <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
        </div>
        <div>
            <label for="address" class="label">Address <span class="font-normal text-slate-400">(optional)</span></label>
            <input id="address" v-model="form.address" type="text" class="input" />
            <p v-if="form.errors.address" class="field-error">{{ form.errors.address }}</p>
        </div>
    </div>
</template>
