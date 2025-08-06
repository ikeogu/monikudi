import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js'; // ✅ FROM node_modules
import { registerSW } from 'virtual:pwa-register';


const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const updateSW = registerSW({
  onNeedRefresh() {
    if (confirm("New content available. Refresh?")) {
      updateSW(true)
    }
  },
  onOfflineReady() {
    console.log("App ready to work offline")
  },
})

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#15263eff',
    },
});


