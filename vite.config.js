import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
         vue()
    ],
    build: {
        manifest: true,
        outDir: 'public/build',
    },
    base: '/build/', // This is important for correct path resolution
    server: {
        https: true,
    }
});
