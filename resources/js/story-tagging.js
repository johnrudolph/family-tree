// Flux's rich text editor (<ui-editor>) is a closed TipTap-based custom
// element with no bundled mention extension and no documented extension
// API. Rather than fight it, this hooks the *public* `editor` property it
// exposes (a real TipTap Editor instance — confirmed by reading Flux Pro's
// bundled JS) to watch for a typed `[[` and drive a plain autocomplete
// popup that inserts literal `[[Full Name]]` text. StoryBodyParser (PHP)
// resolves that text into a link at render time — this file never needs to
// know about links, only about inserting the right characters.

function waitForEditorInstance(editorEl, callback) {
    if (editorEl.editor) {
        callback(editorEl.editor);

        return;
    }

    const interval = setInterval(() => {
        if (editorEl.editor) {
            clearInterval(interval);
            callback(editorEl.editor);
        }
    }, 50);
}

export function initStoryTagging(root, people) {
    const editorEl = root.querySelector('ui-editor');

    if (!editorEl) {
        return;
    }

    let tag = null; // { bracketStart, query }
    let popupEl = null;

    function closePopup() {
        tag = null;

        if (popupEl) {
            popupEl.remove();
            popupEl = null;
        }
    }

    function matches(query) {
        const q = query.trim().toLowerCase();

        return (q === '' ? people : people.filter((p) => p.name.toLowerCase().includes(q))).slice(0, 6);
    }

    function positionPopup() {
        const selection = window.getSelection();

        if (!selection || selection.rangeCount === 0) {
            return;
        }

        const rect = selection.getRangeAt(0).getBoundingClientRect();
        popupEl.style.left = `${rect.left + window.scrollX}px`;
        popupEl.style.top = `${rect.bottom + window.scrollY + 4}px`;
    }

    function selectPerson(editor, person) {
        const { bracketStart } = tag;
        const cursor = editor.state.selection.from;

        editor.chain().focus().insertContentAt({ from: bracketStart, to: cursor }, `${person.name}]] `).run();
        closePopup();
    }

    function renderPopup(editor) {
        const results = matches(tag.query);

        if (results.length === 0) {
            closePopup();

            return;
        }

        if (!popupEl) {
            popupEl = document.createElement('div');
            popupEl.className = 'fixed z-50 max-h-56 w-64 overflow-y-auto rounded-lg border border-zinc-200 bg-white py-1 text-sm shadow-lg dark:border-zinc-700 dark:bg-zinc-800';
            document.body.appendChild(popupEl);
        }

        popupEl.innerHTML = '';

        results.forEach((person) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'block w-full truncate px-3 py-1.5 text-left text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-700';
            item.textContent = person.name;
            // mousedown (not click) fires before the editor's selection is lost to the popup gaining focus.
            item.addEventListener('mousedown', (event) => {
                event.preventDefault();
                selectPerson(editor, person);
            });
            popupEl.appendChild(item);
        });

        positionPopup();
    }

    function handleUpdate(editor) {
        const { state } = editor;
        const pos = state.selection.from;

        if (tag) {
            if (pos < tag.bracketStart) {
                closePopup();

                return;
            }

            const query = state.doc.textBetween(tag.bracketStart, pos, '\n', '\n');

            if (query.includes(']') || query.includes('\n') || query.length > 60) {
                closePopup();

                return;
            }

            tag.query = query;
            renderPopup(editor);

            return;
        }

        const textBefore = state.doc.textBetween(Math.max(0, pos - 2), pos, '\n', '\n');

        if (textBefore === '[[') {
            tag = { bracketStart: pos, query: '' };
            renderPopup(editor);
        }
    }

    waitForEditorInstance(editorEl, (editor) => {
        editor.on('update', ({ editor }) => handleUpdate(editor));
        editor.on('selectionUpdate', ({ editor }) => handleUpdate(editor));
    });

    document.addEventListener('keydown', (event) => {
        if (tag && event.key === 'Escape') {
            closePopup();
        }
    });
}

window.initStoryTagging = initStoryTagging;
