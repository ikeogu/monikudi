<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { formatDate, naira, nairaCompact } from '@/utils/format';

const props = defineProps({
    // [{ date: 'Y-m-d', deposits: Number, withdrawals: Number }]
    days: { type: Array, required: true },
    height: { type: Number, default: 260 },
});

const SERIES = [
    { key: 'deposits', label: 'Deposits', color: '#2a4fd6' },
    { key: 'withdrawals', label: 'Withdrawals', color: '#e5484d' },
];
const PAD = { top: 12, right: 8, bottom: 26, left: 48 };

const container = ref(null);
// Measured from the container before the first draw so the SVG never forces its parent wider.
const width = ref(0);
const hovered = ref(null);
let observer = null;

onMounted(() => {
    observer = new ResizeObserver(([entry]) => (width.value = Math.max(entry.contentRect.width, 240)));
    observer.observe(container.value);
});
onBeforeUnmount(() => observer?.disconnect());

const plotWidth = computed(() => width.value - PAD.left - PAD.right);
const plotHeight = computed(() => props.height - PAD.top - PAD.bottom);
const baseline = computed(() => PAD.top + plotHeight.value);

// Round the top of the axis up to a clean 1/2/2.5/5 x 10^n step.
const scaleMax = computed(() => {
    const max = Math.max(...props.days.flatMap((d) => [d.deposits, d.withdrawals]), 0);
    if (max === 0) return 1000;
    const step = 10 ** Math.floor(Math.log10(max / 4));
    const nice = [1, 2, 2.5, 5, 10].map((m) => m * step).find((s) => s * 4 >= max);
    return nice * 4;
});
const ticks = computed(() => [0, 1, 2, 3, 4].map((i) => (scaleMax.value / 4) * i));
const y = (value) => baseline.value - (value / scaleMax.value) * plotHeight.value;

const slot = computed(() => plotWidth.value / props.days.length);
const barWidth = computed(() => Math.max(2, Math.min(24, (slot.value - 6) / 2)));

const bars = computed(() =>
    props.days.flatMap((day, i) => {
        const groupWidth = barWidth.value * 2 + 2;
        const start = PAD.left + i * slot.value + (slot.value - groupWidth) / 2;
        return SERIES.map((series, s) => {
            const value = day[series.key];
            if (!value) return null;
            const x = start + s * (barWidth.value + 2);
            const top = y(value);
            const r = Math.min(4, barWidth.value / 2, baseline.value - top);
            return {
                key: `${day.date}-${series.key}`,
                color: series.color,
                d: `M${x},${baseline.value} V${top + r} A${r},${r} 0 0 1 ${x + r},${top} H${x + barWidth.value - r} A${r},${r} 0 0 1 ${x + barWidth.value},${top + r} V${baseline.value} Z`,
            };
        }).filter(Boolean);
    }),
);

// Label roughly weekly so the axis stays readable on phones.
const labelEvery = computed(() => Math.max(1, Math.ceil(props.days.length / Math.floor(plotWidth.value / 56))));
const xLabels = computed(() =>
    props.days
        .map((day, i) => ({ i, day }))
        .filter(({ i }) => i % labelEvery.value === 0)
        .map(({ i, day }) => ({ x: PAD.left + i * slot.value + slot.value / 2, text: formatDate(day.date, { year: undefined }) })),
);

const tooltip = computed(() => {
    if (hovered.value === null) return null;
    const day = props.days[hovered.value];
    const center = PAD.left + hovered.value * slot.value + slot.value / 2;
    return { day, left: Math.min(Math.max(center, 90), width.value - 90) };
});
</script>

<template>
    <div>
        <div class="mb-3 flex items-center gap-4 text-xs text-slate-600">
            <span v-for="series in SERIES" :key="series.key" class="inline-flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-sm" :style="{ background: series.color }" />
                {{ series.label }}
            </span>
        </div>

        <div ref="container" class="relative w-full min-w-0" :style="{ height: `${height}px` }" @mouseleave="hovered = null">
            <svg v-if="width" :width="width" :height="height" class="block" role="img" aria-label="Daily deposits and withdrawals this month">
                <g>
                    <template v-for="tick in ticks" :key="tick">
                        <line :x1="PAD.left" :x2="width - PAD.right" :y1="y(tick)" :y2="y(tick)" stroke="#e2e8f0" stroke-width="1" />
                        <text :x="PAD.left - 8" :y="y(tick)" text-anchor="end" dominant-baseline="middle" class="fill-slate-500 text-[11px]">
                            {{ tick === 0 ? '0' : nairaCompact(tick) }}
                        </text>
                    </template>
                </g>

                <rect
                    v-if="hovered !== null"
                    :x="PAD.left + hovered * slot"
                    :y="PAD.top"
                    :width="slot"
                    :height="plotHeight"
                    class="fill-slate-100"
                />

                <path v-for="bar in bars" :key="bar.key" :d="bar.d" :fill="bar.color" />

                <text
                    v-for="label in xLabels"
                    :key="label.x"
                    :x="label.x"
                    :y="height - 6"
                    text-anchor="middle"
                    class="fill-slate-500 text-[11px]"
                >
                    {{ label.text }}
                </text>

                <rect
                    v-for="(day, i) in days"
                    :key="day.date"
                    :x="PAD.left + i * slot"
                    :y="PAD.top"
                    :width="slot"
                    :height="plotHeight"
                    fill="transparent"
                    @mouseenter="hovered = i"
                    @touchstart.passive="hovered = i"
                />
            </svg>

            <div
                v-if="tooltip"
                class="pointer-events-none absolute top-0 z-10 w-44 -translate-x-1/2 rounded-lg bg-white px-3 py-2 text-xs shadow-lg ring-1 ring-slate-200"
                :style="{ left: `${tooltip.left}px` }"
            >
                <p class="mb-1 font-semibold text-slate-900">{{ formatDate(tooltip.day.date) }}</p>
                <p v-for="series in SERIES" :key="series.key" class="flex items-center justify-between gap-2 text-slate-600">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-sm" :style="{ background: series.color }" />{{ series.label }}
                    </span>
                    <span class="font-medium text-slate-900">{{ naira(tooltip.day[series.key]) }}</span>
                </p>
            </div>
        </div>

        <table class="sr-only">
            <caption>Daily deposits and withdrawals</caption>
            <thead><tr><th>Date</th><th>Deposits</th><th>Withdrawals</th></tr></thead>
            <tbody>
                <tr v-for="day in days" :key="day.date">
                    <td>{{ formatDate(day.date) }}</td><td>{{ naira(day.deposits) }}</td><td>{{ naira(day.withdrawals) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
