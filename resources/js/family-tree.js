import * as f3 from 'family-chart';
import 'family-chart/styles/family-chart.css';

let chart = null;

/**
 * Mount an interactive family tree into `container`. Clicking a card
 * recenters the tree on that person and notifies the page's right-hand
 * panel (via a window CustomEvent) so it can show that person's details
 * and the "add a relationship" form — see pages/tree/index.blade.php.
 */
export function initFamilyTree(container, data, { mainId } = {}) {
    if (!data.length) {
        container.innerHTML = '<p class="text-zinc-500 p-4">No one on the tree yet.</p>';
        return null;
    }

    // family-chart's entire stylesheet (cursor, hover, card styling) is scoped
    // under this class — it isn't applied to the container automatically.
    container.classList.add('f3');

    chart = f3.createChart(container, data);

    // family-chart defaults to a 2000ms transition (plus a per-level stagger
    // on top of that for the initial render) — reads as sluggish/choppy for
    // something as frequent as clicking around a tree. Snap it up.
    chart.setTransitionTime(300);

    // Don't show a ghost "add" placeholder card for an unrecorded second
    // parent — our data model doesn't assume every child has two parents on record.
    chart.setSingleParentEmptyCard(false);

    // family-chart hides the selected person's siblings by default. We want
    // the opposite: selecting anyone should show their parents, siblings,
    // spouses, and children all at once, not make relatives disappear.
    chart.setShowSiblingsOfMain(true);

    // Without this, sibling (and child) order isn't stable — it can shift
    // depending on who's selected as "main". Sort by birth date so order is
    // always chronological and never rearranges when you click a sibling.
    // People with an unknown dob sort last, after everyone with a known one.
    chart.setSortChildrenFunction((a, b) => {
        const aDob = a.data.dob_sort;
        const bDob = b.data.dob_sort;

        if (!aDob && !bDob) return 0;
        if (!aDob) return 1;
        if (!bDob) return -1;

        return aDob < bDob ? -1 : aDob > bDob ? 1 : 0;
    });

    if (mainId) {
        chart.updateMainId(String(mainId));
    }

    chart
        .setCardHtml()
        // "imageCircleRect" only shows the round photo card for people who
        // actually have one — everyone else gets a plain name card instead
        // of a generic silhouette placeholder.
        .setStyle('imageCircleRect')
        .setCardDisplay([['first name', 'last name'], ['birthday']])
        .setCardImageField('avatar')
        // Give people with a linked user account a visible outline, so it's
        // obvious at a glance who's actually joined vs. who's tree-only.
        .setOnCardUpdate(function (d) {
            this.querySelector('.card-inner')?.classList.toggle('has-account', !!d.data.data.has_account);
        })
        .setOnCardClick((e, d) => {
            // Don't animate here too — this dispatch round-trips through Livewire
            // (see x-on:family-tree-card-click in tree/index.blade.php), which
            // comes back and calls familyTreeCenterOn() below. Animating both on
            // click and again once the round-trip lands was firing two competing
            // transitions and made the tree feel choppy.
            window.dispatchEvent(new CustomEvent('family-tree-card-click', { detail: { id: d.data.id } }));
        });

    chart.updateTree({ initial: true });

    return chart;
}

/** Recenter the already-mounted tree on a person, e.g. after a search selection. */
export function familyTreeCenterOn(id) {
    if (!chart) return;
    chart.updateMainId(String(id));
    chart.updateTree({ tree_position: 'main_to_middle' });
}

/** Refresh the tree's underlying data, e.g. after adding a relationship from the panel. */
export function familyTreeUpdateData(data) {
    if (!chart) return;
    chart.updateData(data);
    chart.updateTree();
}

window.initFamilyTree = initFamilyTree;
window.familyTreeCenterOn = familyTreeCenterOn;
window.familyTreeUpdateData = familyTreeUpdateData;
