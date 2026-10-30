import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Grace Daily Contribution navy, sampled from the client's design.
                brand: {
                    50: '#eef3ff',
                    100: '#dce6ff',
                    200: '#c0d1ff',
                    300: '#94b1fd',
                    400: '#6187f8',
                    500: '#3c63ef',
                    600: '#2a4fd6',
                    700: '#2340b2',
                    800: '#1f3790',
                    900: '#1d3274',
                    950: '#141f47',
                },
            },
        },
    },

    plugins: [forms],
};
