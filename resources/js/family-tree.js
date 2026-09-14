import * as f3 from 'family-chart';
import 'family-chart/styles/family-chart.css';

/**
 * Mount an interactive family tree into `container`, using data already
 * serialized by App\Support\FamilyTreeSerializer::toChartData(). Clicking a
 * card navigates to that person's wiki page instead of family-chart's default
 * "set as main person" behavior, since the tree is a navigation surface here.
 */
export function initFamilyTree(container, data, { mainId } = {}) {
    if (!data.length) {
        container.innerHTML = '<p class="text-zinc-500 p-4">No one on the tree yet.</p>';
        return null;
    }

    const chart = f3.createChart(container, data);

    if (mainId) {
        chart.updateMainId(String(mainId));
    }

    const card = chart
        .setCardHtml()
        .setCardDisplay([['first name', 'last name'], ['birthday']])
        .setCardImageField('avatar')
        .setOnCardClick((e, d) => {
            if (d.data.data.url) {
                window.Livewire ? window.Livewire.navigate(d.data.data.url) : (window.location = d.data.data.url);
            }
        });

    chart.updateTree({ initial: true });

    return chart;
}

window.initFamilyTree = initFamilyTree;
