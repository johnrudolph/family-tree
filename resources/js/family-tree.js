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

    // Don't show a ghost "add" placeholder card for an unrecorded second
    // parent — our data model doesn't assume every child has two parents on record.
    chart.setSingleParentEmptyCard(false);

    if (mainId) {
        chart.updateMainId(String(mainId));
    }

    chart
        .setCardHtml()
        .setCardDisplay([['first name', 'last name'], ['birthday']])
        .setCardImageField('avatar')
        .setOnCardClick((e, d) => {
            const id = d.data.id;
            chart.updateMainId(id);
            chart.updateTree({ tree_position: 'main_to_middle' });
            window.dispatchEvent(new CustomEvent('family-tree-card-click', { detail: { id } }));
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
