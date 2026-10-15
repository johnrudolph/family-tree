/**
 * Pin coordinates are stored as percentages of the photo's own pixel
 * dimensions, but the <img> is shown scaled and letterboxed (object-contain
 * inside a box that isn't necessarily the same aspect ratio) — this maps
 * between "percentage of the actual picture" and "pixels inside the <img>
 * element's box" so pins line up with the visible image regardless of how
 * the surrounding layout sizes that box.
 */
function visiblePictureRect(img) {
    const boxWidth = img.clientWidth;
    const boxHeight = img.clientHeight;
    const naturalWidth = img.naturalWidth || boxWidth;
    const naturalHeight = img.naturalHeight || boxHeight;
    const scale = Math.min(boxWidth / naturalWidth, boxHeight / naturalHeight) || 1;
    const width = naturalWidth * scale;
    const height = naturalHeight * scale;

    return {
        left: (boxWidth - width) / 2,
        top: (boxHeight - height) / 2,
        width,
        height,
    };
}

/**
 * event.offsetX/Y are already relative to the <img>'s own padding box, so
 * no getBoundingClientRect() math is needed for the click itself — just
 * translate that point into a percentage of the visible picture.
 */
function photoPinCoordinatesFromClick(event) {
    const rect = visiblePictureRect(event.target);
    const x = ((event.offsetX - rect.left) / rect.width) * 100;
    const y = ((event.offsetY - rect.top) / rect.height) * 100;

    return {
        x: Math.max(0, Math.min(100, x)),
        y: Math.max(0, Math.min(100, y)),
    };
}

/**
 * Pins are rendered as siblings of the <img>, absolutely positioned within
 * the same `relative` wrapper — so their pixel position has to account for
 * both the image's offset within that wrapper and the letterboxing above.
 */
function photoPinStyle(img, xPercent, yPercent) {
    const rect = visiblePictureRect(img);
    const left = img.offsetLeft + rect.left + (xPercent / 100) * rect.width;
    const top = img.offsetTop + rect.top + (yPercent / 100) * rect.height;

    return `left: ${left}px; top: ${top}px;`;
}

window.photoPinCoordinatesFromClick = photoPinCoordinatesFromClick;
window.photoPinStyle = photoPinStyle;
