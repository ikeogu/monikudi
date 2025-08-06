<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
});
</script>

<template>
    <Head title="Welcome to Grace and Zion Monikudi LTD" />

    <div class="bg-gray-50 text-black dark:bg-gray-900 dark:text-white min-h-screen relative">
        <!-- Decorative background -->
        <div class="absolute inset-0 overflow-hidden">
            <img
                id="background"
                class="w-full h-full object-cover opacity-5"
                src="https://laravel.com/assets/img/welcome/background.svg"
                alt="Background"
            />
        </div>

        <!-- Page container -->
        <div class="relative z-10 flex flex-col min-h-screen">
            <!-- Header -->
            <header class="w-full px-6 py-4 flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Grace and Zion Monikudi LTD</h1>

                <nav v-if="canLogin" class="space-x-4">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="text-sm font-medium text-gray-700 hover:text-indigo-700 dark:text-white dark:hover:text-indigo-300"
                        >
                            Log in
                        </Link>
                       <!--
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 px-4 py-2 rounded-md transition"
                        >
                            Register
                        </Link>
                        -->
                    </template>
                </nav>
            </header>

            <!-- Hero Section -->
            <main class="flex-1 flex flex-col items-center justify-center text-center px-6">
                <h2 class="text-4xl md:text-5xl font-extrabold text-gray-900 dark:text-white leading-tight mb-4">
                    Your Trusted Partner in Daily Financial Records
                </h2>
                <p class="text-lg text-gray-700 dark:text-gray-300 max-w-xl mb-6">
                    Grace and Zion Monikudi LTD helps businesses and individuals keep a clean record of all credit and debit transactions with simplicity, clarity, and trust.
                </p>
                <div class="flex flex-col sm:flex-row gap-4">
                   
                    <Link
                        v-if="canLogin"
                        :href="route('login')"
                        class="px-6 py-3 text-indigo-700 bg-white hover:bg-gray-100 dark:bg-gray-800 dark:text-indigo-400 dark:hover:bg-gray-700 rounded-md text-lg transition border border-indigo-200"
                    >
                        Log In
                    </Link>
                </div>
            </main>

            <!-- Features Section -->
            <section class="py-16 bg-white dark:bg-gray-800 px-6">
                <div class="max-w-6xl mx-auto text-center">
                    <h3 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Why Choose Us?</h3>
                    <div class="grid md:grid-cols-3 gap-8 mt-8 text-left">
                        <div>
                            <h4 class="text-lg font-bold text-indigo-600 dark:text-indigo-400">Daily Ledger</h4>
                            <p class="text-gray-700 dark:text-gray-300 mt-2">Easily record daily transactions for all your customers with timestamps and auto totals.</p>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold text-indigo-600 dark:text-indigo-400">Receipts & Reports</h4>
                            <p class="text-gray-700 dark:text-gray-300 mt-2">Generate printable receipts and track balances instantly for every customer.</p>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold text-indigo-600 dark:text-indigo-400">Simple Dashboard</h4>
                            <p class="text-gray-700 dark:text-gray-300 mt-2">Access all your customers, transactions, and reports from one clean dashboard.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Footer -->
            <footer class="text-center text-sm text-gray-500 dark:text-gray-400 py-6">
                &copy; {{ new Date().getFullYear() }} Grace and Zion Monikudi LTD. Built with Laravel {{ laravelVersion }} & PHP {{ phpVersion }}.
            </footer>
        </div>
    </div>
</template>
