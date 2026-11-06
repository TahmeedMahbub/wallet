/*
 * Alpine.js is supplied by Livewire (@livewireScripts) and must not be
 * imported again here. Small page behaviours are registered as Alpine data.
 */

/**
 * Show digits with Indian grouping while typing: 1,95,000 or 450.50.
 * A plain global function (used as x-data="moneyField()") so it works no matter
 * when Alpine starts relative to this file.
 */
window.moneyField = () => ({
    busy: false,

    format(el) {
        if (this.busy) {
            return;
        }

        const raw = el.value.replace(/[^\d.]/g, '');
        const parts = raw.split('.');
        const whole = parts[0].replace(/^0+(?=\d)/, '');
        const grouped = whole.length > 3
            ? whole.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + whole.slice(-3)
            : whole;
        const next = grouped + (parts.length > 1 ? '.' + parts[1].slice(0, 2) : '');

        if (el.value === next) {
            return;
        }

        // Wait until Livewire has stored the raw text, then write the grouped
        // text and tell Livewire again so the server state and screen agree.
        this.busy = true;
        setTimeout(() => {
            el.value = next;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            this.busy = false;
        }, 0);
    },
});

/**
 * Friendly text for a date input value: "Nov 15, 2026".
 */
window.walletDateOnly = (value) => {
    if (!value) {
        return '';
    }

    const date = new Date(`${value}T00:00:00`);

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

/**
 * Friendly text for a datetime-local value: "Today, Oct 31 · 6:56 PM",
 * "Yesterday, Oct 30 · 9:07 PM" or "Nov 1 · 10:00 AM".
 */
window.walletDateLabel = (value) => {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    const now = new Date();
    const sameDay = (a, b) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    const yesterday = new Date(now);
    yesterday.setDate(now.getDate() - 1);

    const day = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    const time = date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });

    if (sameDay(date, now)) {
        return `Today, ${day} · ${time}`;
    }

    if (sameDay(date, yesterday)) {
        return `Yesterday, ${day} · ${time}`;
    }

    return `${day} · ${time}`;
};

/*
 * Installable app. The service worker (public/sw.js) caches only the static app shell;
 * it never stores pages or money data. Wallet cannot save anything offline: while the
 * device is offline, actions that need the server are stopped and the person is told.
 */
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    });
}

const offlineMessage = "You're offline. Wallet can't save changes until you reconnect.";
const tellPerson = (message) => window.dispatchEvent(new CustomEvent('toast', { detail: { message } }));

window.addEventListener('offline', () => tellPerson(offlineMessage));
window.addEventListener('online', () => tellPerson('Back online.'));

const needsServer = (element) => {
    for (let node = element; node instanceof Element; node = node.parentElement) {
        if ([...node.attributes].some((attribute) => attribute.name.startsWith('wire:click') || attribute.name.startsWith('wire:submit'))) {
            return true;
        }
    }

    return false;
};

const stopWhenOffline = (event) => {
    if (navigator.onLine) {
        return;
    }

    if (event.type === 'submit' || needsServer(event.target)) {
        event.preventDefault();
        event.stopPropagation();
        tellPerson(offlineMessage);
    }
};

document.addEventListener('submit', stopWhenOffline, true);
document.addEventListener('click', stopWhenOffline, true);
