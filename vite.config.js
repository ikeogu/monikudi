import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        VitePWA({
            registerType: 'autoUpdate',
            includeAssets: ['favicon.svg', 'robots.txt'],
            manifest: {
                name: 'Grace  & Zion Monikudi',
                short_name: 'Grace & Zion Monikudi',
                start_url: '/',
                display: 'standalone',
                background_color: '#ffffff',
                theme_color: '#0f172a',
                icons: [
                {
                    src: '/img/pwa-192x192.png',
                    sizes: '192x192',
                    type: 'image/png'
                },
                {
                    src: '/img/pwa-512x512.png',
                    sizes: '512x512',
                    type: 'image/png'
                }
                ]
            },
            workbox: {
                runtimeCaching: [
                
                {
                    urlPattern: /^https:\/\/fonts\.googleapis\.com\/.*/i,
                    handler: 'CacheFirst',
                    options: {
                    cacheName: 'google-fonts-stylesheets',
                    expiration: {
                        maxEntries: 10,
                        maxAgeSeconds: 60 * 60 * 24 * 365, // 1 year
                    },
                    },
                },
                {
                    urlPattern: /^https:\/\/fonts\.gstatic\.com\/.*/i,
                    handler: 'CacheFirst',
                    options: {
                    cacheName: 'google-fonts-webfonts',
                    expiration: {
                        maxEntries: 10,
                        maxAgeSeconds: 60 * 60 * 24 * 365,
                    },
                    },
                },
                {
                    urlPattern: ({ request }) => request.mode === 'navigate',
                    handler: 'NetworkFirst',
                    options: {
                        cacheName: 'pages-cache',
                        networkTimeoutSeconds: 3,
                        expiration: {
                        maxEntries: 50,
                        maxAgeSeconds: 86400
                        },
                        fallback: '/offline.html'
                    }
                }
                ]
            }
            })
    ],
});
