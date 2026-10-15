/**
 * Independent per-word matching for the person command palettes: each
 * space-separated word in the query must appear somewhere in the item's
 * text, but words don't need to be contiguous — "john drexler" still
 * matches "John Rudolph Drexler" even though the middle name is skipped.
 * Flux's built-in command filter only does a single contiguous substring
 * match, so filtering is done manually here (flux:command filter="manual")
 * by toggling the same `data-hidden` attribute Flux uses internally.
 */
function normalizeSearchText(value) {
    return (value ?? '')
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase()
        .trim();
}

function filterPersonSearchResults(event) {
    const root = event.target.closest('[data-flux-command]');

    if (! root) {
        return;
    }

    const tokens = normalizeSearchText(event.target.value).split(/\s+/).filter(Boolean);

    root.querySelectorAll('[data-flux-command-item]').forEach((item) => {
        const text = normalizeSearchText(item.textContent);
        const matches = tokens.every((token) => text.includes(token));

        item.toggleAttribute('data-hidden', ! matches);
    });

    // filter="manual" (needed for our own per-word matching above) tells
    // Flux not to auto-highlight an item as you type — normally its own
    // built-in filter does that. Without it nothing is "active", so arrowing
    // down starts from scratch and Enter does nothing. Re-highlight the
    // first visible result ourselves, same as Flux's own filter does.
    root._activatable?.clearActive();
    root._activatable?.activateFirst();
}

window.filterPersonSearchResults = filterPersonSearchResults;
