import * as f3 from 'family-chart';
import { select } from 'd3';
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

    configureCardAppearance(chart).setOnCardClick((e, d) => {
        // Don't animate here too — this dispatch round-trips through Livewire
        // (see x-on:family-tree-card-click in tree/index.blade.php), which
        // comes back and calls familyTreeCenterOn() below. Animating both on
        // click and again once the round-trip lands was firing two competing
        // transitions and made the tree feel choppy.
        window.dispatchEvent(new CustomEvent('family-tree-card-click', { detail: { id: d.data.id } }));
    });

    chart.updateTree({ initial: true });

    setupTrackpadPanning(container);

    return chart;
}

/**
 * A small, read-only family-chart scoped to one generation up/down from a
 * person — used on the person page as a simplified visual in place of a
 * plain relationship list. Clicking a card navigates straight to that
 * person's page instead of feeding a side panel.
 */
export function initPersonFamilyWidget(container, data, mainId) {
    if (!data.length) {
        container.innerHTML = '<p class="text-zinc-500 p-4">No relationships yet.</p>';
        return null;
    }

    container.classList.add('f3');

    const widget = f3.createChart(container, data);
    widget.setTransitionTime(300);
    widget.setSingleParentEmptyCard(false);
    widget.setShowSiblingsOfMain(true);
    widget.setAncestryDepth(1);
    widget.setProgenyDepth(1);
    widget.updateMainId(String(mainId));

    configureCardAppearance(widget).setOnCardClick((e, d) => {
        const url = d.data.data.url;
        if (!url) return;

        if (window.Livewire?.navigate) {
            window.Livewire.navigate(url);
        } else {
            window.location.href = url;
        }
    });

    widget.updateTree({ initial: true });

    setupTrackpadPanning(container);

    // Stashed on the element so a later relationship change can refresh this
    // exact instance — see updatePersonFamilyWidget().
    container._familyWidget = widget;

    return widget;
}

/** Refresh an already-mounted person-page widget, e.g. after adding/removing a relationship. */
export function updatePersonFamilyWidget(container, data) {
    const widget = container?._familyWidget;
    if (!widget) return;

    widget.updateData(data);
    widget.updateTree();
}

/**
 * Card style/display shared by the full tree and the person-page widget:
 * a plain name card for anyone with no photo (instead of a generic
 * silhouette), and a persistent outline for anyone with a linked account.
 */
function configureCardAppearance(chartInstance) {
    return chartInstance
        .setCardHtml()
        .setStyle('imageCircleRect')
        .setCardDisplay([['first name', 'last name'], ['birthday']])
        .setCardImageField('avatar')
        .setOnCardUpdate(function (d) {
            this.querySelector('.card-inner')?.classList.toggle('has-account', !!d.data.data.has_account);
        });
}

/**
 * family-chart's zoom (via d3-zoom) only ever zooms on a wheel event — a
 * plain two-finger trackpad scroll and pinch-to-zoom both just scale the
 * tree, there's no panning. Trackpad pinch sends a synthetic ctrlKey with
 * its wheel events (the standard way browsers distinguish the two
 * gestures), so: ctrlKey → let family-chart's own zoom handle it as
 * before; otherwise, pan by the scroll delta ourselves.
 */
function setupTrackpadPanning(container) {
    const canvas = container.querySelector('#f3Canvas');
    if (!canvas) return;

    canvas.addEventListener(
        'wheel',
        (event) => {
            if (event.ctrlKey) {
                // A pinch gesture always arrives as a ctrlKey wheel event.
                // d3-zoom (which family-chart uses internally) only calls
                // preventDefault() itself when the zoom level actually
                // changes — right at its min/max zoom it silently no-ops,
                // so the browser's own page-zoom kicks in instead. Claim it
                // ourselves unconditionally so that can never happen;
                // family-chart's own zoom handler still runs after this.
                event.preventDefault();

                return;
            }

            const zoom = canvas.__zoomObj;
            if (!zoom) return;

            event.preventDefault();
            event.stopImmediatePropagation();

            const k = canvas.__zoom ? canvas.__zoom.k : 1;
            select(canvas).call(zoom.translateBy, -event.deltaX / k, -event.deltaY / k);
        },
        { capture: true, passive: false },
    );
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
window.initPersonFamilyWidget = initPersonFamilyWidget;
window.updatePersonFamilyWidget = updatePersonFamilyWidget;
