document.addEventListener('DOMContentLoaded', () => {
    const elements = Array.from(document.querySelectorAll('[data-posted-at]'));

    if (!elements.length) {
        return;
    }

    const UNITS = [
        { limit: 3600, seconds: 60, single: 'minute', plural: 'minutes' },
        { limit: 86400, seconds: 3600, single: 'hour', plural: 'hours' },
        { limit: 2592000, seconds: 86400, single: 'day', plural: 'days' },
        { limit: 31536000, seconds: 2592000, single: 'month', plural: 'months' },
        { limit: Infinity, seconds: 31536000, single: 'year', plural: 'years' },
    ];

    // Mirrors App\Support\RelativeTime so the wording matches the server render.
    const label = (postedAt, prefix) => {
        const posted = new Date(postedAt).getTime();

        if (Number.isNaN(posted)) {
            return null;
        }

        const seconds = Math.floor((Date.now() - posted) / 1000);

        if (seconds < 60) {
            return `${prefix}Just now`;
        }

        const unit = UNITS.find((candidate) => seconds < candidate.limit);
        const count = Math.floor(seconds / unit.seconds);
        const noun = count === 1 ? unit.single : unit.plural;

        return `${prefix}${count} ${noun} ago`;
    };

    const refresh = () => {
        elements.forEach((element) => {
            const text = label(element.dataset.postedAt, element.dataset.postedPrefix ?? '');

            if (text !== null && element.textContent !== text) {
                element.textContent = text;
            }
        });
    };

    refresh();

    // Keep the copy honest while the page stays open. Document visibility is
    // checked so a backgrounded tab is not repainted pointlessly.
    window.setInterval(() => {
        if (document.visibilityState === 'visible') {
            refresh();
        }
    }, 30000);
});