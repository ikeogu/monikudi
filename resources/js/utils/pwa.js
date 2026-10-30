import { ref } from 'vue';

/** The deferred browser install prompt, when the app is installable and not yet installed. */
export const installPrompt = ref(null);
export const isOnline = ref(typeof navigator === 'undefined' ? true : navigator.onLine);

export function registerServiceWorker() {
    if (typeof window === 'undefined') return;

    window.addEventListener('online', () => (isOnline.value = true));
    window.addEventListener('offline', () => (isOnline.value = false));

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        installPrompt.value = event;
    });
    window.addEventListener('appinstalled', () => (installPrompt.value = null));

    // The dev server serves unhashed modules that a cache would pin; only register for real builds.
    if ('serviceWorker' in navigator && import.meta.env.PROD) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch((error) => {
                console.warn('Service worker registration failed', error);
            });
        });
    }
}

export async function installApp() {
    if (!installPrompt.value) return;
    installPrompt.value.prompt();
    await installPrompt.value.userChoice;
    installPrompt.value = null;
}
