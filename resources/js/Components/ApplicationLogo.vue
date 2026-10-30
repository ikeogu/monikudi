<script setup>
// Grace Daily Contribution mark: a "G" holding three savers. Uses currentColor so it
// works on white and on the navy sidebar; --logo-accent tints the people.
</script>

<template>
    <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M50.4 13.6A26 26 0 1 0 58 33H48.5" stroke="currentColor" stroke-width="7" stroke-linecap="round" stroke-linejoin="round" />
        <g :style="{ fill: 'var(--logo-accent, currentColor)' }" transform="translate(-2 0)">
            <circle cx="23" cy="24.5" r="3.4" />
            <circle cx="31" cy="21.5" r="3.9" />
            <circle cx="39" cy="24.5" r="3.4" />
            <path d="M18.5 37v-4.5a4.5 4.5 0 0 1 9 0V37z" />
            <path d="M26 37v-6a5 5 0 0 1 10 0v6z" />
            <path d="M34.5 37v-4.5a4.5 4.5 0 0 1 9 0V37z" />
        </g>
        <path transform="translate(-2 0)" d="M15 40.5c5 4.5 12 6 19 4.5 3-.6 5.5-2 7.5-3.5" :style="{ stroke: 'var(--logo-accent, currentColor)' }" stroke-width="3.2" stroke-linecap="round" />
    </svg>
</template>
