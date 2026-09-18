// Shared full-screen photo viewer with prev/next navigation, used by both
// the story show page (server-rendered gallery) and the timeline's story
// modal (client-rendered gallery). One overlay, lazily built on first use,
// reused across calls rather than rebuilt each time.

let root = null;
let images = [];
let index = 0;

function build() {
    const el = document.createElement('div');
    el.className = 'fixed inset-0 z-[70] hidden items-center justify-center bg-black/90 p-4';
    el.setAttribute('data-lightbox', '');
    el.innerHTML = `
        <button type="button" data-lightbox-close class="absolute right-4 top-4 text-white/70 hover:text-white">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-8"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L10.94 12l-5.72 5.72a.75.75 0 1 0 1.06 1.06L12 13.06l5.72 5.72a.75.75 0 1 0 1.06-1.06L13.06 12l5.72-5.72a.75.75 0 0 0-1.06-1.06L12 10.94 6.28 5.22Z"/></svg>
        </button>
        <button type="button" data-lightbox-prev class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full p-2 text-white/70 hover:text-white sm:left-4">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-8"><path fill-rule="evenodd" d="M15.79 4.21a.75.75 0 0 1 0 1.06L8.06 13l7.73 7.73a.75.75 0 1 1-1.06 1.06l-8.25-8.25a.75.75 0 0 1 0-1.06l8.25-8.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>
        </button>
        <img data-lightbox-image class="max-h-[85vh] max-w-[85vw] rounded object-contain" alt="">
        <button type="button" data-lightbox-next class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full p-2 text-white/70 hover:text-white sm:right-4">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-8"><path fill-rule="evenodd" d="M8.21 4.21a.75.75 0 0 1 1.06 0l8.25 8.25a.75.75 0 0 1 0 1.06l-8.25 8.25a.75.75 0 0 1-1.06-1.06L15.94 13 8.21 5.27a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
        </button>
        <div data-lightbox-counter class="absolute bottom-4 left-1/2 -translate-x-1/2 text-sm text-white/70"></div>
    `;
    document.body.appendChild(el);

    el.querySelector('[data-lightbox-close]').addEventListener('click', close);
    el.querySelector('[data-lightbox-prev]').addEventListener('click', prev);
    el.querySelector('[data-lightbox-next]').addEventListener('click', next);
    el.addEventListener('click', (event) => {
        if (event.target === el) close();
    });

    document.addEventListener('keydown', (event) => {
        if (el.classList.contains('hidden')) return;

        if (event.key === 'Escape') close();
        if (event.key === 'ArrowLeft') prev();
        if (event.key === 'ArrowRight') next();
    });

    return el;
}

function render() {
    root.querySelector('[data-lightbox-image]').src = images[index];

    const multi = images.length > 1;
    root.querySelector('[data-lightbox-prev]').style.display = multi ? '' : 'none';
    root.querySelector('[data-lightbox-next]').style.display = multi ? '' : 'none';
    root.querySelector('[data-lightbox-counter]').textContent = multi ? `${index + 1} / ${images.length}` : '';
}

function prev() {
    index = (index - 1 + images.length) % images.length;
    render();
}

function next() {
    index = (index + 1) % images.length;
    render();
}

function close() {
    root.classList.add('hidden');
    root.classList.remove('flex');
}

export function openLightbox(urls, startIndex = 0) {
    if (!urls || urls.length === 0) return;

    root = root || build();
    images = urls;
    index = startIndex;
    render();
    root.classList.remove('hidden');
    root.classList.add('flex');
}

window.openLightbox = openLightbox;
