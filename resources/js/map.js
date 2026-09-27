import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const MARKER_COLORS = {
    birth: '#22c55e',
    death: '#71717a',
    story: '#3b82f6',
};

function markerIcon(type) {
    const color = MARKER_COLORS[type] ?? MARKER_COLORS.story;

    return L.divIcon({
        className: '',
        html: `<span style="display:block;width:14px;height:14px;border-radius:9999px;background:${color};border:2px solid white;box-shadow:0 0 0 1px rgba(0,0,0,0.25);"></span>`,
        iconSize: [14, 14],
        iconAnchor: [7, 7],
    });
}

function formatDate(point) {
    const date = new Date(point.date);

    if (point.date_precision === 'year') {
        return String(date.getUTCFullYear());
    }

    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC' });
}

function popupHtml(point) {
    const image = point.featured_image_url ?? point.avatar_url;

    return `
        <div style="min-width:170px">
            ${image ? `<img src="${image}" style="width:100%;height:96px;object-fit:cover;border-radius:6px;margin-bottom:6px" alt="">` : ''}
            <a href="${point.url}" data-map-navigate style="font-weight:600;color:#2563eb;text-decoration:none">${point.title}</a>
            <div style="font-size:12px;color:#71717a;margin-top:2px">${formatDate(point)} &middot; ${point.location_label}</div>
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

export function initMap(root, points) {
    const map = L.map(root).setView([20, 0], 2);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19,
    }).addTo(map);

    const markers = points.map((point) => L.marker([point.latitude, point.longitude], { icon: markerIcon(point.type) })
        .bindPopup(popupHtml(point))
        .addTo(map));

    if (markers.length > 0) {
        map.fitBounds(L.featureGroup(markers).getBounds().pad(0.2));
    }

    map.on('popupopen', (e) => {
        e.popup.getElement()?.querySelector('[data-map-navigate]')?.addEventListener('click', (event) => {
            event.preventDefault();
            navigateTo(event.currentTarget.href);
        });
    });

    window.addEventListener('resize', () => map.invalidateSize());
}

window.initMap = initMap;
