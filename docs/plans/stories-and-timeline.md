# Stories + Timeline — Implementation Plan

Status: **IMPLEMENTING — decisions locked, working through phases below.**
Last updated: 2026-09-17

## Why this doc exists

Two big, interconnected features requested in one go. This doc is the
resumable source of truth across sessions — if context runs out, read
this file first, check the Progress Log at the bottom, and continue
from the first unchecked item in the current phase.

## The two features

1. **Stories become timeline-ready**: every story gets a start date
   (required) and optional end date, a tightly constrained title,
   inline images with a selectable featured image, and inline
   `[[Person Name]]`-style tagging of people (linking to their person
   page, and showing "tagged in" on the person's page and the tree's
   right panel).
2. **Timeline view**: a new zoomable, pannable timeline showing births,
   deaths, and stories (dots for point-in-time, spans for date ranges),
   anchored to the bottom of the viewport, starting fully zoomed out
   (earliest event on the left, today on the right), with decade →
   year → month gridlines depending on zoom level, pinch-to-zoom plus
   a zoom-level meter, and clickable leader-line cards that open a
   full modal for stories.

## Existing groundwork (confirmed by reading the codebase)

- `Story` model already exists: `title`, `slug`, `body` (sanitized
  HTML from `flux:editor`), `gallery` media collection (Spatie
  MediaLibrary), `people()` belongs-to-many pivot (`story_person`),
  full wiki workflow (revisions, suggestions, editors) via
  `HasWikiWorkflow`. No dates, no featured-image concept, no inline
  tagging yet.
- `Person` already has `dob`/`dob_precision` (exact/approx/unknown),
  `dod`, `birth_city`/`death_city`. Exactly the data the timeline
  needs for birth/death events — no schema change needed there.
- The rich text editor is **Flux Pro's `<flux:editor>`**, a closed
  custom element (`<ui-editor>`) backed by TipTap under the hood
  (confirmed via `vendor/livewire/flux-pro/dist/editor.js` — bundles
  `@tiptap/core`, `extension-bold`, `-link`, `-heading`, etc.). No
  bundled mention extension, no bundled image extension, no known
  public API for registering custom TipTap extensions. **Decision:
  don't fight this** — inline tagging uses a plain `[[Name]]` text
  syntax we parse ourselves, and images stay in the existing gallery
  (see Decisions below).
- `family-chart` (D3-based) is already a dependency; the app already
  has a working custom-zoom/pan pattern in
  `resources/js/family-tree.js` using `d3-zoom` directly — the
  timeline reuses this pattern rather than adding a timeline library.
- Media/photo privacy pattern to reuse: `Person::photoUrl()` issues
  short-lived signed R2 URLs; same approach applies to story images
  and any avatar shown on timeline birth/death events.
- No stories exist in production yet — schema can require `start_date`
  outright, no backfill/migration-safety dance needed.

## Decisions (locked 2026-09-17)

- **Inline person tagging**: `[[Person Name]]` typed directly in the
  story body (plain text inside the TipTap doc). A custom
  Alpine-built autocomplete popup triggers on typing `[[`, lists
  matching people, and inserts `[[Exact Name]]` at the cursor on
  selection. At render/display time, a parser resolves `[[...]]`
  tokens against real people and renders them as links to the
  person's page; unresolved brackets render as plain text (fail
  open, never a broken link). This is also how "tagged in" gets
  computed — no separate pivot bookkeeping needed, the body is the
  source of truth (see Data model).
- **Inline images**: dropped. Images stay in the existing
  gallery-only model. A featured-image picker is added on top of the
  gallery (click a gallery photo to mark it featured); the featured
  image is what shows on the timeline card.
- **Date precision**: stories get `start_date_precision` /
  `end_date_precision`, mirroring `Person.dob_precision`
  (exact/approx/unknown). Timeline renders "c. 1954" style labels for
  non-exact dates.
- **Title length**: 70 characters, enforced in validation on
  create/edit/suggest.
- **No dates on existing stories**: not a concern — nothing in
  production yet, so `start_date` is `NOT NULL` from the start with
  no backfill logic.
- **Timeline engine**: hand-rolled with `d3-zoom` + `d3-scale`,
  matching the family tree's existing custom pan/zoom precedent. No
  new JS dependency.
- **"Tagged in" display**: both the person show page (new section,
  alongside the existing `$person->stories` list — worth checking
  whether these should merge into one list now that "about" and
  "tagged in" might diverge, see Phase 2 note) and the family tree's
  right panel when that person is selected.

## Data model plan

- `stories` table migration: `start_date` (date, **not null**),
  `start_date_precision` (string, default `'exact'`), `end_date`
  (date, nullable), `end_date_precision` (string, nullable). Title
  validation capped at 70 in the Livewire components (existing
  `string` 255 DB column is fine, no need to shrink it).
- Featured image: Spatie MediaLibrary custom property. Mark one
  gallery media item `featured => true` per story; clearing any
  previous flag when a new one is chosen (loop + `setCustomProperty`
  + `save()`, or `clearMediaCollectionExcept`-style helper). Add
  `Story::featuredImage(): ?Media` accessor (`getMedia('gallery')`
  filtered by custom property, falling back to the first gallery
  image if none is explicitly featured, falling back to null).
- Inline person tags: no new table. `App\Support\StoryBodyParser`
  (or similar) exposes:
  - `render(string $body): string` — HTML-safe transform of
    `[[Name]]` tokens into `<a href="...">Name</a>` for display,
    matched against `Person::fullName()`/`legalName()` case-
    insensitively; ambiguous or no-match tokens render as plain
    text (not a link).
  - `taggedPersonIds(string $body): Collection<int>` — resolves the
    same tokens to person IDs, used both to compute "tagged in" and
    to feed the timeline's story→person association without a
    stored pivot (though see open implementation question below on
    whether we still want a derived/cached pivot for query
    performance once there are many stories).
- New `events` concept for the timeline: computed/serialized on read
  from existing `Person` (dob/dod) and `Story` (start/end date)
  records, similar in spirit to `FamilyTreeSerializer`. New
  `App\Support\TimelineSerializer` assembling a unified JSON shape:
  `{ type: 'birth'|'death'|'story', date, date_precision, end_date?,
  end_date_precision?, title, person_id?, story_id?, avatar_url?,
  featured_image_url?, city? }`.

## Open implementation questions (non-blocking, resolve while building)

- [ ] Autocomplete popup for `[[` needs a caret-position-aware insert
      into Flux's closed editor. Need to check at Phase 2 start
      whether `<ui-editor>` exposes any DOM/JS surface (e.g. a
      `.editor` property, ProseMirror view access, or at minimum a
      focused `contenteditable` we can measure/insert into via the
      Selection API) — if truly opaque, fallback is a plain
      `<flux:textarea>` for story bodies instead of `flux:editor`,
      trading rich formatting for a workable tagging UX. Investigate
      before writing the popup.
- [ ] Whether `story_person` (explicit "who is this about") and
      body-derived tags should merge into one list or stay distinct
      concepts ("about" = intentional/curated, "tagged in" = every
      mention). Leaning toward keeping both — "about" stays the
      editor-curated multi-select (unchanged), "tagged in" is
      strictly the derived body mentions, and a person page can show
      both sections if they differ.
- [ ] Timeline event density/decluttering strategy at low zoom (don't
      render every event) — design once Phase 4 UI work starts,
      likely bucket-by-pixel-distance and show a "+N more" cluster
      marker.

## Implementation phases

### Phase 1 — Story schema + featured image + title constraint — ✅ DONE (2026-09-17)
- [x] Migration: `start_date` (not null), `start_date_precision`
      (default `exact`), `end_date`, `end_date_precision` on
      `stories`. No backfill needed — table was empty.
- [x] `Story` model: fillable + `date` casts + docblock updates,
      `featuredImage()` / `featureImage(Media $media)`.
- [x] Title validation cap (70) wired into create/edit/suggest forms
      (`maxlength` attr + `max:70` validation rule).
- [x] Date + precision inputs on create/edit/suggest forms. Note:
      checked and `Person.dob_precision` actually has **no dedicated
      form control anywhere** in the app today — it's silently
      inferred as `exact`/`unknown` from whether a date was typed.
      Stories needed a real precision picker since dates are
      required, so this introduces the first explicit
      exact/year-only radio-button pattern in the app (values
      `exact`/`year`, not `exact`/`approx`/`unknown` — simpler than
      `Person`'s enum since a story always has *some* known date).
      Year-only stores `{year}-01-01` and displays as just the year.
- [x] Featured-image picker: gallery grid in the edit form, click a
      photo to toggle `featured` (Spatie MediaLibrary custom
      property, not a new column) — mutual exclusivity enforced in
      `Story::featureImage()`.
- [x] Updated `stories.suggestions` field-label map for the 4 new
      fields.
- [x] Story index/show pages now display start–end dates (year-only
      formatted without a day/month) instead of just `created_at`.
- [x] Tests added to `StoryPageTest.php`: year-only + end-date
      creation, title-length rejection, featured-image toggle/mutual
      exclusivity. Full suite (174 tests), Pint, and Larastan all
      green.

### Phase 2 — Inline person tagging — ✅ DONE (2026-09-17)
- [x] **Editor-insertion question resolved, and it's good news.**
      Flux's closed `<ui-editor>` custom element exposes a plain,
      *public* `this.editor` property holding the real TipTap `Editor`
      instance (confirmed by reading the bundled JS — `this.editor =
      new Editor({...})`, not a `#private` field). That means no
      custom TipTap extension was needed at all:
      `resources/js/story-tagging.js` polls for
      `document.querySelector('ui-editor').editor`, listens to its
      `update`/`selectionUpdate` events, and on typing `[[` renders a
      plain Alpine-free positioned `<div>` popup listing matching
      people (via `getBoundingClientRect()` on the current
      `Selection` for placement). Picking a person calls
      `editor.chain().focus().insertContentAt({from, to}, text).run()`
      to replace the typed query with the full bracketed name — same
      TipTap command API Flux's own toolbar buttons use, so it's not
      fighting the editor, just driving its public surface.
      Wired into create/edit/suggest via a `wire:ignore` wrapper (same
      pattern as the family-chart widget) so Livewire re-renders never
      tear down the popup wiring.
- [x] `App\Support\StoryBodyParser`: `render()` (link resolution,
      case-insensitive, unresolved tags render as literal
      `[[bracketed text]]` rather than a broken link) and
      `taggedPersonIds()` / `storiesTagging(Person)`. 5 unit tests.
- [x] "Tagged in" merged into the existing "Stories" section on the
      person show page (`relatedStories` computed = curated "about"
      pivot ∪ body-derived tags, deduped) rather than a second
      section — reads more naturally as "stories you show up in."
- [x] Same merge-and-show pattern added to the tree's right panel
      (`selectedPersonStories` computed) under a new "Stories"
      heading.
- [x] Tests for both surfaces, StoryBodyParser, and the create/edit
      flow. Full suite (181 tests), Pint, Larastan all green.
- [x] **Actually verified in a real browser**, not just Pest: spun up
      a throwaway user with a real TOTP secret (Google2FA, computed
      the live OTP via tinker), drove Playwright through login → 2FA
      → story creation → typing `[[Jane` → clicking the popup result
      → publishing → confirmed the rendered link, the person page's
      "tagged in" story, and the tree right panel's "tagged in" story
      all appeared correctly, in both light and dark mode. Test
      data cleaned up afterward. Screenshots aren't kept in the repo
      (scratchpad only) but the flow is now known-working end to end,
      not just type-checked.

### Phase 3 — Timeline data layer
- [ ] `TimelineSerializer` unifying birth/death + story events into
      one JSON payload, consent/privacy-safe (reuse
      `Person::photoUrl()` pattern).
- [ ] Route + Livewire full-page component `pages::timeline.index`.
- [ ] Tests: birth/death event generation from Person records
      (including precision handling), story dot vs. span
      classification, ordering.

### Phase 4 — Timeline UI
- [ ] `resources/js/timeline.js`: D3-based zoom/pan, decade → year →
      month gridline switching by zoom level, initial viewport
      (earliest event → today, right-anchored to "now").
- [ ] Zoom-level meter control (UI element showing/controlling current
      zoom, in addition to pinch/wheel).
- [ ] Event rendering: dot (point event) vs. span (ranged story) on
      the baseline, vertical leader line up to a card (title +
      featured image or avatar).
- [ ] Clustering/decluttering at low zoom (see open question above).
- [ ] Click → full-story modal (reuse existing story show content) or
      navigate to person page for birth/death events.
- [ ] Sidebar nav entry + route.
- [ ] Manual browser verification (per this repo's UI-change
      convention) — launch dev server, actually zoom/pan/click through
      it, check both light and dark mode.

## Progress log

- **2026-09-17**: Plan drafted from initial feature request. Read
  existing `Story` model/views, confirmed Flux editor is TipTap-based
  with no bundled mention/image extensions and no known extensibility
  hook. Asked user clarifying questions; all decisions above are
  locked. No prod story data exists, so schema can require
  `start_date` outright. Nothing implemented yet — starting Phase 1
  next.
