import * as f3 from 'family-chart';
import 'family-chart/styles/family-chart.css';

let modalEl = null;

function initials(first, last) {
    return [first, last].filter(Boolean).map((s) => s[0]).join('').toUpperCase();
}

function buildModal() {
    if (modalEl) return modalEl;

    modalEl = document.createElement('div');
    modalEl.className = 'fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4';
    modalEl.innerHTML = `
        <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl dark:bg-zinc-800" data-card>
            <div class="flex items-center gap-4">
                <div data-avatar class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-zinc-200 text-lg font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-200"></div>
                <div class="min-w-0">
                    <p data-name class="truncate text-lg font-medium text-zinc-900 dark:text-zinc-100"></p>
                    <p data-meta class="text-sm text-zinc-500 dark:text-zinc-400"></p>
                </div>
            </div>
            <div class="mt-6 flex gap-2">
                <a data-view class="flex-1 rounded-lg bg-zinc-900 px-3 py-2 text-center text-sm font-medium text-white hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white">View page</a>
                <a data-add-rel class="flex-1 rounded-lg border border-zinc-300 px-3 py-2 text-center text-sm font-medium text-zinc-700 hover:border-zinc-400 dark:border-zinc-600 dark:text-zinc-200 dark:hover:border-zinc-500">Add relationship</a>
            </div>
            <button type="button" data-close class="mt-4 w-full text-center text-sm text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">Close</button>
        </div>
    `;
    document.body.appendChild(modalEl);

    modalEl.addEventListener('click', (e) => {
        if (e.target === modalEl || e.target.closest('[data-close]')) hideModal();
    });

    return modalEl;
}

function hideModal() {
    if (modalEl) modalEl.classList.add('hidden');
}

function showPersonCard(treeDatum) {
    const modal = buildModal();
    const d = treeDatum.data.data;
    const firstName = d['first name'] || '';
    const lastName = d['last name'] || '';

    const avatar = modal.querySelector('[data-avatar]');
    if (d.avatar) {
        avatar.innerHTML = `<img src="${d.avatar}" class="size-full object-cover" alt="">`;
    } else {
        avatar.innerHTML = '';
        avatar.textContent = initials(firstName, lastName);
    }

    modal.querySelector('[data-name]').textContent = [firstName, lastName].filter(Boolean).join(' ');

    const status = d.living ? 'Living' : 'Deceased';
    const year = d.birthday ? ` · Born ${d.birthday}` : '';
    modal.querySelector('[data-meta]').textContent = status + year;

    const viewLink = modal.querySelector('[data-view]');
    const addRelLink = modal.querySelector('[data-add-rel]');
    if (d.url) {
        viewLink.href = d.url;
        addRelLink.href = d.url.replace(/\/?$/, '/relationships');
        viewLink.classList.remove('pointer-events-none', 'opacity-50');
        addRelLink.classList.remove('pointer-events-none', 'opacity-50');
    } else {
        viewLink.removeAttribute('href');
        addRelLink.removeAttribute('href');
        viewLink.classList.add('pointer-events-none', 'opacity-50');
        addRelLink.classList.add('pointer-events-none', 'opacity-50');
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

/**
 * Mount an interactive family tree into `container`, using data already
 * serialized by App\Support\FamilyTreeSerializer::toChartData(). Clicking a
 * card opens a quick-info card for that person (photo, name, status) with
 * actions to view their full page or add a relationship — the tree view
 * itself already shows relationships, so the card doesn't repeat them.
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

    chart
        .setCardHtml()
        .setCardDisplay([['first name', 'last name'], ['birthday']])
        .setCardImageField('avatar')
        .setOnCardClick((e, d) => showPersonCard(d));

    chart.updateTree({ initial: true });

    return chart;
}

window.initFamilyTree = initFamilyTree;
