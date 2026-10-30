import { entryDateTime, formatDate, naira, typeLabel } from './format';

/**
 * Nigerian numbers are usually saved as 0803...; WhatsApp needs 234803...
 */
export function internationalPhone(phone) {
    const digits = String(phone || '').replace(/\D/g, '');
    if (!digits) return '';
    if (digits.startsWith('234')) return digits;
    if (digits.startsWith('0') && digits.length === 11) return `234${digits.slice(1)}`;
    return digits;
}

/** Opens a chat with the customer, or the contact picker when there is no phone number. */
export function whatsappUrl(phone, text) {
    const to = internationalPhone(phone);
    return `https://wa.me/${to}?text=${encodeURIComponent(text)}`;
}

export function receiptText(transaction, business) {
    const customer = transaction.customer;
    const lines = [
        `*${business.business_name.toUpperCase()}*`,
        `Receipt: ${transaction.receipt_no}`,
        '',
        `Date: ${entryDateTime(transaction)}`,
        `Customer: ${customer.reg_no} - ${customer.full_name}`,
        `Type: ${typeLabel(transaction.type)}`,
        `Amount: *${naira(transaction.amount)}*`,
    ];
    if (transaction.balance_after !== null && transaction.balance_after !== undefined) {
        lines.push(`Balance: ${naira(transaction.balance_after)}`);
    }
    if (transaction.collector) {
        lines.push(`Collected by: ${transaction.collector.name}`);
    }
    lines.push('', business.receipt_footer || '', business.tagline || '');

    return lines.join('\n').trim();
}

export function statementText(customer, summary, transactions, business) {
    const recent = transactions.slice(0, 10).map(
        (t) => `${formatDate(t.date)}  ${typeLabel(t.type)}  ${naira(t.amount)}  (Bal ${naira(t.running_balance)})`,
    );

    return [
        `*${business.business_name.toUpperCase()}*`,
        'Customer Statement',
        '',
        `${customer.reg_no} - ${customer.full_name}`,
        `Total deposits: ${naira(summary.deposits)}`,
        `Total withdrawals: ${naira(summary.withdrawals)}`,
        `Current balance: *${naira(summary.balance)}*`,
        ...(recent.length ? ['', 'Recent entries:', ...recent] : []),
        '',
        business.tagline || '',
    ]
        .join('\n')
        .trim();
}
