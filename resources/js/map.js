import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const MARKER_COLORS = {
    birth: '#22c55e',
    death: '#71717a',
    story: '#3b82f6',
    mixed: '#7c3aed',
};

function colorFor(points) {
    const types = new Set(points.map((point) => point.type));
    return MARKER_COLORS[types.size === 1 ? points[0].type : 'mixed'] ?? MARKER_COLORS.story;
}

function markerIcon(points) {
    const color = colorFor(points);
    const count = points.length;
    const size = count > 1 ? 18 : 14;

    const badge = count > 1
        ? `<span style="position:absolute;top:-6px;left:-6px;min-width:16px;height:16px;padding:0 3px;border-radius:9999px;background:#27272a;color:white;font-size:10px;line-height:16px;text-align:center;font-weight:600;box-shadow:0 0 0 1px white;">${count}</span>`
        : '';

    return L.divIcon({
        className: '',
        html: `
            <span style="position:relative;display:block;width:${size}px;height:${size}px">
                <span style="display:block;width:100%;height:100%;border-radius:9999px;background:${color};border:2px solid white;box-shadow:0 0 0 1px rgba(0,0,0,0.25);"></span>
                ${badge}
            </span>
        `,
        iconSize: [size, size],
        iconAnchor: [size / 2, size / 2],
    });
}

function formatDate(point) {
    const date = new Date(point.date);

    if (point.date_precision === 'year') {
        return String(date.getUTCFullYear());
    }

    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC' });
}

function popupEntryHtml(point, { compact }) {
    const image = point.featured_image_url ?? point.avatar_url;

    if (!compact) {
        return `
            <div style="min-width:170px">
                ${image ? `<img src="${image}" style="width:100%;height:96px;object-fit:cover;border-radius:6px;margin-bottom:6px" alt="">` : ''}
                <a href="${point.url}" data-map-navigate style="font-weight:600;color:#2563eb;text-decoration:none">${point.title}</a>
                <div style="font-size:12px;color:#71717a;margin-top:2px">${formatDate(point)} &middot; ${point.location_label}</div>
            </div>
        `;
    }

    return `
        <a href="${point.url}" data-map-navigate style="display:flex;gap:8px;align-items:center;padding:6px 0;text-decoration:none;color:inherit;border-bottom:1px solid rgba(0,0,0,0.08)">
            ${image ? `<img src="${image}" style="width:32px;height:32px;object-fit:cover;border-radius:4px;flex-shrink:0" alt="">` : ''}
            <div style="min-width:0">
                <div style="font-weight:600;color:#2563eb;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${point.title}</div>
                <div style="font-size:11px;color:#71717a">${formatDate(point)}</div>
            </div>
        </a>
    `;
}

/**
 * Many births/deaths/stories can share the exact same geocoded location
 * (a city-precision place, or a family hospital) — one marker per group,
 * with every event listed in its popup, instead of only the last marker
 * drawn at that spot being clickable.
 */
function popupHtml(points) {
    if (points.length === 1) {
        return popupEntryHtml(points[0], { compact: false });
    }

    const entries = points.map((point) => popupEntryHtml(point, { compact: true })).join('');

    return `
        <div style="min-width:220px;max-width:260px">
            <div style="font-weight:600;margin-bottom:4px">${points[0].location_label} <span style="font-weight:400;color:#71717a">(${points.length})</span></div>
            <div style="max-height:240px;overflow-y:auto">${entries}</div>
        </div>
    `;
}

function navigateTo(url) {
    if (window.Livewire?.navigate) {
        window.Livewire.navigate(url);
    } else {
        window.location.href = url;
    }
}

/**
 * @return Map<string, object[]>
 */
function groupByLocation(points) {
    const groups = new Map();

    points.forEach((point) => {
        const key = `${point.latitude},${point.longitude}`;

        if (!groups.has(key)) {
            groups.set(key, []);
        }

        groups.get(key).push(point);
    });

    return groups;
}

export function initMap(root, points) {
    const map = L.map(root).setView([20, 0], 2);

    // Esri's public Light Gray Canvas tiles — free, no API key/signup, and
    // deliberately minimal (a plain gray canvas with roads/labels, no
    // airport/shop/etc. POI icons), which suits plotting people/places over
    // basemap clutter better than OSM's own default tiles.
    // (CARTO's Positron tiles are the other well-known free minimal style,
    // but now require a signed-up API key; Wikimedia's tiles 403 outside
    // Wikimedia's own sites.) Base (fill/roads) + Reference (labels), per
    // Esri's own layering for this basemap.
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}', {
        attribution: '&copy; <a href="https://www.esri.com">Esri</a>',
        maxZoom: 16,
    }).addTo(map);

    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Reference/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 16,
    }).addTo(map);

    const markers = Array.from(groupByLocation(points).values()).map((groupPoints) => L.marker(
        [groupPoints[0].latitude, groupPoints[0].longitude],
        { icon: markerIcon(groupPoints) },
    ).bindPopup(popupHtml(groupPoints)).addTo(map));

    if (markers.length > 0) {
        map.fitBounds(L.featureGroup(markers).getBounds().pad(0.2));
    }

    map.on('popupopen', (e) => {
        e.popup.getElement()?.querySelectorAll('[data-map-navigate]').forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                navigateTo(event.currentTarget.href);
            });
        });
    });

    window.addEventListener('resize', () => map.invalidateSize());
}

window.initMap = initMap;
