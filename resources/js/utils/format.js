const nairaFormatter = new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' });
const compactFormatter = new Intl.NumberFormat('en-NG', { notation: 'compact', maximumFractionDigits: 1 });

export const DEPOSIT = 'credit';
export const WITHDRAWAL = 'debit';

/** ₦1,234.50 */
export function naira(value) {
    return nairaFormatter.format(Number(value || 0));
}

/** ₦286.8K, for chart axes and tight spaces. */
export function nairaCompact(value) {
    return `₦${compactFormatter.format(Number(value || 0))}`;
}

/** "+₦1,000.00" for deposits, "-₦500.00" for withdrawals. */
export function signedNaira(transaction) {
    return `${isDeposit(transaction) ? '+' : '-'}${naira(transaction.amount)}`;
}

export function isDeposit(transaction) {
    return transaction.type === DEPOSIT;
}

export function typeLabel(type) {
    return type === DEPOSIT ? 'Deposit' : 'Withdrawal';
}

/**
 * Accepts a plain Y-m-d date (parsed as a local date so it never shifts a day)
 * or a full ISO timestamp.
 */
function toDate(value) {
    if (!value) return null;
    if (value instanceof Date) return value;
    const plain = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value).slice(0, 10));
    if (plain && !String(value).includes('T')) {
        return new Date(Number(plain[1]), Number(plain[2]) - 1, Number(plain[3]));
    }
    return new Date(value);
}

/** 27 May 2025 */
export function formatDate(value, options = {}) {
    const date = toDate(value);
    return date ? date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', ...options }) : '';
}

/** 27/05/2025 */
export function formatShortDate(value) {
    const date = toDate(value);
    return date ? date.toLocaleDateString('en-GB') : '';
}

/** 10:42 AM */
export function formatTime(value) {
    const date = toDate(value);
    return date ? date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }) : '';
}

/** Business date of the entry plus the time it was recorded: "27 May 2025, 10:42 AM". */
export function entryDateTime(transaction) {
    return `${formatDate(transaction.date)}, ${formatTime(transaction.created_at)}`;
}

export function initials(name = '') {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');
}

export function fileSize(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
