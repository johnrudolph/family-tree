import { select, scaleTime, zoom as d3zoom, zoomIdentity, timeYear, timeMonth, timeFormat } from 'd3';

const CARD_MIN_GAP_PX = 110;
const BASELINE_OFFSET_PX = 56;
const ROW_HEIGHT_PX = 52;

let zoomBehavior = null;
let currentTransform = null;

function formatEventDate(event) {
    const date = new Date(event.date);

    if (event.date_precision === 'year') {
        return String(date.getUTCFullYear());
    }

    return timeFormat('%B %-d, %Y')(date);
}

function gridlineGenerator(scale, width) {
    const [start, end] = scale.domain();
    const spanDays = (end - start) / 86400000;

    if (spanDays > 365 * 10) {
        return { ticks: timeYear.every(10).range(start, end), format: timeFormat('%Y'), tier: 'decade' };
    }

    if (spanDays > 365 * 2) {
        return { ticks: timeYear.every(1).range(start, end), format: timeFormat('%Y'), tier: 'year' };
    }

    return { ticks: timeMonth.every(1).range(start, end), format: timeFormat('%b %Y'), tier: 'month' };
}

function eventIcon(event) {
    const src = event.avatar_url || event.featured_image_url;

    if (!src) return '';

    return `<img src="${src}" class="size-8 rounded-full object-cover shrink-0" alt="">`;
}

function formatEventDateRange(event) {
    let label = formatEventDate(event);

    if (event.end_date) {
        label += ` – ${formatEventDate({ date: event.end_date, date_precision: event.end_date_precision })}`;
    }

    return label;
}

function openStoryModal(root, event) {
    const modal = root.querySelector('[data-timeline-modal]');
    const body = root.querySelector('[data-timeline-modal-body]');
    const title = root.querySelector('[data-timeline-modal-title]');
    const date = root.querySelector('[data-timeline-modal-date]');
    const image = root.querySelector('[data-timeline-modal-image]');

    title.textContent = event.title;
    date.textContent = formatEventDateRange(event);
    body.innerHTML = event.body_html || '';

    if (event.featured_image_url) {
        image.src = event.featured_image_url;
        image.classList.remove('hidden');
    } else {
        image.classList.add('hidden');
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeStoryModal(root) {
    const modal = root.querySelector('[data-timeline-modal]');
    modal?.classList.add('hidden');
    modal?.classList.remove('flex');
}

function navigateTo(url) {
    if (window.Livewire?.navigate) {
        window.Livewire.navigate(url);
    } else {
        window.location.href = url;
    }
}

function render(root, events, scale) {
    const width = root.clientWidth;
    const height = root.clientHeight;
    const baselineY = height - BASELINE_OFFSET_PX;

    const svg = select(root).select('svg.timeline-svg');
    svg.attr('width', width).attr('height', height);

    // Baseline.
    svg.select('line.timeline-baseline')
        .attr('x1', 0).attr('x2', width)
        .attr('y1', baselineY).attr('y2', baselineY);

    // "Today" marker — always the right edge of the initial view, but stays
    // pinned to its real date as the user pans/zooms away from it.
    const todayX = scale(new Date());
    svg.select('line.timeline-today')
        .attr('x1', todayX).attr('x2', todayX)
        .attr('y1', 0).attr('y2', baselineY)
        .style('display', todayX >= 0 && todayX <= width ? null : 'none');

    // Gridlines, tiered by how much time is currently visible.
    const { ticks, format, tier } = gridlineGenerator(scale, width);
    const gridGroup = svg.select('g.timeline-gridlines');
    const gridSel = gridGroup.selectAll('g.tick').data(ticks, (d) => tier + d.getTime());

    gridSel.exit().remove();

    const gridEnter = gridSel.enter().append('g').attr('class', 'tick');
    gridEnter.append('line');
    gridEnter.append('text');

    const gridAll = gridEnter.merge(gridSel);
    gridAll.attr('transform', (d) => `translate(${scale(d)}, 0)`);
    gridAll.select('line')
        .attr('y1', 0).attr('y2', baselineY)
        .attr('class', 'stroke-zinc-200 dark:stroke-zinc-700');
    gridAll.select('text')
        .attr('y', baselineY + 16)
        .attr('text-anchor', 'middle')
        .attr('class', 'fill-zinc-500 text-[11px]')
        .text((d) => format(d));

    // Dots (point events) and spans (ranged story events) — always drawn for
    // every event, regardless of whether it gets a card (declutter only
    // affects cards, never the baseline marks themselves).
    const dots = events.filter((e) => !e.end_date);
    const spans = events.filter((e) => e.end_date);

    const spanSel = svg.select('g.timeline-spans').selectAll('line.span').data(spans, (d) => d.story_id);
    spanSel.exit().remove();
    spanSel.enter().append('line').attr('class', 'span stroke-blue-400 dark:stroke-blue-500')
        .attr('stroke-width', 4).attr('stroke-linecap', 'round')
        .merge(spanSel)
        .attr('y1', baselineY).attr('y2', baselineY)
        .attr('x1', (d) => scale(new Date(d.date)))
        .attr('x2', (d) => scale(new Date(d.end_date)));

    const dotSel = svg.select('g.timeline-dots').selectAll('circle.dot').data(dots, (d) => `${d.type}-${d.person_id || d.story_id}`);
    dotSel.exit().remove();
    dotSel.enter().append('circle').attr('class', (d) => `dot ${d.type === 'story' ? 'fill-blue-500' : 'fill-zinc-400 dark:fill-zinc-500'}`)
        .attr('r', 4)
        .merge(dotSel)
        .attr('cy', baselineY)
        .attr('cx', (d) => scale(new Date(d.date)));

    // Cards: declutter by minimum pixel gap, ordered left to right. Every
    // event still has a dot/span above — this only decides which ones also
    // get a title+image card. Two cards that would collide horizontally
    // don't just hide one — the second is pushed up to the next row instead,
    // stacking on longer leader lines. A card is only ever hidden outright
    // once every row runs out of vertical room.
    const maxRows = Math.max(1, Math.floor((baselineY - 40) / ROW_HEIGHT_PX));
    const rowLastX = new Array(maxRows).fill(-Infinity);
    const allSorted = [...events].sort((a, b) => scale(new Date(a.date)) - scale(new Date(b.date)));
    const shown = [];

    for (const event of allSorted) {
        const x = scale(new Date(event.date));
        if (x < -50 || x > width + 50) continue;

        const row = rowLastX.findIndex((lastX) => x - lastX >= CARD_MIN_GAP_PX);
        if (row === -1) continue;

        rowLastX[row] = x;
        shown.push({ event, x, row });
    }

    const cardLayer = select(root).select('.timeline-cards');
    const cardSel = cardLayer.selectAll('.timeline-card').data(shown, (d) => `${d.event.type}-${d.event.person_id || d.event.story_id}`);

    cardSel.exit().remove();

    const cardEnter = cardSel.enter().append('div').attr('class', 'timeline-card pointer-events-auto absolute flex flex-col items-center');

    cardEnter.append('div').attr('class', 'timeline-card-line w-px bg-zinc-300 dark:bg-zinc-600');
    cardEnter.append('button')
        .attr('type', 'button')
        .attr('class', 'timeline-card-box flex max-w-[10rem] items-center gap-2 rounded-lg border border-zinc-200 bg-white p-1.5 text-left shadow-sm hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600')
        .attr('data-event-type', (d) => d.event.type)
        .html((d) => `
            ${eventIcon(d.event)}
            <span class="min-w-0">
                <span class="block truncate text-xs font-medium text-zinc-800 dark:text-zinc-100">${d.event.title}</span>
                <span class="block text-[10px] text-zinc-500">${formatEventDate(d.event)}</span>
            </span>
        `)
        .on('click', (pointerEvent, d) => {
            if (d.event.type === 'story') {
                openStoryModal(root, d.event);
            } else {
                navigateTo(d.event.url);
            }
        });

    const cardAll = cardEnter.merge(cardSel);
    cardAll.style('left', (d) => `${d.x}px`).style('bottom', (d) => `${height - baselineY + 8 + d.row * ROW_HEIGHT_PX}px`);
    cardAll.select('.timeline-card-line').style('height', (d) => `${20 + d.row * ROW_HEIGHT_PX}px`);
}

export function initTimeline(root, events) {
    if (!events.length) {
        root.innerHTML = '<p class="p-4 text-zinc-500">Nothing on the timeline yet.</p>';
        return;
    }

    root.classList.add('relative', 'overflow-hidden');

    const earliest = new Date(Math.min(...events.map((e) => new Date(e.date).getTime())));
    const today = new Date();

    const width = root.clientWidth;
    const x0 = scaleTime().domain([earliest, today]).range([0, width]);

    let x = x0;

    const redraw = () => render(root, events, x);

    const height = root.clientHeight;

    zoomBehavior = d3zoom()
        .scaleExtent([1, 400])
        .extent([[0, 0], [width, height]])
        .translateExtent([[0, 0], [width, height]])
        .on('zoom', (zoomEvent) => {
            currentTransform = zoomEvent.transform;
            x = currentTransform.rescaleX(x0);
            redraw();
            updateZoomMeter(root, currentTransform.k);
        });

    const svgSel = select(root).select('svg.timeline-svg');
    svgSel.call(zoomBehavior);
    svgSel.call(zoomBehavior.transform, zoomIdentity);

    // Two-finger trackpad scroll pans; pinch (ctrlKey wheel) and mouse wheel
    // zoom via d3's own handler — same split used by the family tree.
    root.querySelector('svg.timeline-svg').addEventListener(
        'wheel',
        (wheelEvent) => {
            if (wheelEvent.ctrlKey) return;

            wheelEvent.preventDefault();
            wheelEvent.stopImmediatePropagation();
            svgSel.call(zoomBehavior.translateBy, -wheelEvent.deltaX / (currentTransform?.k || 1), 0);
        },
        { capture: true, passive: false },
    );

    root.querySelector('[data-timeline-modal-close]')?.addEventListener('click', () => closeStoryModal(root));
    root.querySelector('[data-timeline-modal]')?.addEventListener('click', (e) => {
        if (e.target.hasAttribute('data-timeline-modal')) closeStoryModal(root);
    });

    const meter = root.querySelector('[data-timeline-zoom-meter]');
    meter?.addEventListener('input', (inputEvent) => {
        const k = Number(inputEvent.target.value);
        svgSel.call(zoomBehavior.scaleTo, k);
    });

    redraw();

    window.addEventListener('resize', redraw);
}

function updateZoomMeter(root, k) {
    const meter = root.querySelector('[data-timeline-zoom-meter]');
    if (meter && document.activeElement !== meter) {
        meter.value = String(k);
    }
}

window.initTimeline = initTimeline;
