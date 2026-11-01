import { onBeforeUnmount, onMounted, ref, watchEffect } from 'vue';

const STORAGE_KEY = 'receipt-paper';

/** The roll width chosen on this device, shared by receipts and statements. */
export const paper = ref(readPaper());

function readPaper() {
    try {
        return localStorage.getItem(STORAGE_KEY) === '80' ? '80' : '58';
    } catch {
        return '58';
    }
}

export function setPaper(size) {
    paper.value = size;
    try {
        localStorage.setItem(STORAGE_KEY, size);
    } catch {}
}

/**
 * Page rules for a thermal roll. The printer driver supplies the roll as the paper size,
 * so only the margins go; the slip itself is sized to the printable width.
 */
export const SLIP_PAGE_CSS = '@media print { @page { margin: 0; } }';

/** Keeps a <style> tag in <head> in step with css() while the component is mounted. */
export function usePageStyle(css) {
    let style = null;
    let stop = null;

    onMounted(() => {
        style = document.createElement('style');
        document.head.appendChild(style);
        stop = watchEffect(() => (style.textContent = css()));
    });

    onBeforeUnmount(() => {
        stop?.();
        style?.remove();
    });
}
