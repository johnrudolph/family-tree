# Stories + Timeline — Implementation Plan

Status: **COMPLETE — all four phases shipped and verified 2026-09-17.**
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

### Phase 3 — Timeline data layer — DONE (2026-09-17)
- [x] `App\Support\TimelineSerializer::events()` — plain-array
      assembly (not `Collection`, to sidestep PHPStan/Larastan's
      generic-covariance complaints about merging differently-shaped
      array literals) unifying birth events, death events, and story
      events, sorted by date via `usort`. Each event carries a
      uniform key set (type, date, date_precision, end_date,
      end_date_precision, title, person_id, story_id, avatar_url,
      featured_image_url, body_html, url) so the client never has to
      branch on shape. Birth/death titles append "in {city}" when
      birth_city/death_city is set. Reuses `Person::photoUrl()` as-is
      for avatars — already consent-safe by construction (a
      non-consented living person can never have a photo uploaded in
      the first place, so no extra gating needed here). `body_html`
      on story events is pre-rendered via `StoryBodyParser::render()`
      so the timeline's modal never needs a Livewire round-trip to
      show a story.
- [x] Route `timeline` -> `pages::timeline.index`, inside the
      existing `auth + verified + two-factor.enabled` group. Sidebar
      nav entry added (clock icon, between Family Tree and People).
- [x] Tests (`TimelineSerializerTest.php`, `TimelinePageTest.php`):
      birth/death event shape and city-suffixed titles, no-dob/no-dod
      exclusion, dot-vs-span classification via end_date presence,
      chronological ordering across mixed types, featured-image
      inclusion, route auth-gating. 190 tests total, Pint, Larastan
      all green.

### Phase 4 — Timeline UI — DONE (2026-09-17)
- [x] `resources/js/timeline.js`: hand-rolled with d3-zoom +
      d3-scaleTime, following the exact pattern already established
      in `family-tree.js` (raw wheel listener splitting two-finger
      trackpad pan from ctrlKey-tagged pinch/mouse-wheel zoom, which
      falls through to d3-zoom's own handler). Initial view: x0
      domain is [earliest event date, now] mapped to [0, width] with
      the identity transform — this alone satisfies "always start
      fully zoomed out, earliest on the left, today on the right," no
      special-casing needed. A dashed "today" line is drawn at its
      real scaled position (stays correct as you pan away from it,
      not pinned to the edge).
- [x] Gridlines are tiered by currently-visible day-span: >10 years ->
      decade ticks, >2 years -> year ticks, else -> month ticks —
      "show whatever is coherently readable," recomputed every
      zoom/pan frame.
- [x] Zoom-level meter: an input[type=range] in the header, two-way
      bound to the d3-zoom transform (scaleTo on input, slider value
      updated on every zoom event unless the user is actively
      dragging it).
- [x] Every event always gets a dot (point) or a thicker rounded span
      (ranged story, drawn between start_date/end_date) on the
      baseline — declutter only ever affects cards, never the
      baseline marks. Cards are chosen left-to-right with a minimum
      110px pixel gap between consecutively shown cards; skipped
      events still have their dot/span, just no card, and become
      individually clickable again once zooming spreads them out
      past the gap threshold.
- [x] Card -> vertical leader line -> title + avatar/featured-image
      (falls back to a emoji badge when there's no image). Clicking a
      story card opens a hand-rolled modal (title + pre-rendered
      body_html + featured image, no Flux dependency since this is
      driven from vanilla JS outside Livewire's request cycle);
      clicking a birth/death card navigates to that person's page via
      Livewire.navigate (same fallback pattern as family-tree.js's
      widget click handler).
- [x] Sidebar nav entry + route (done as part of Phase 3 above).
- [x] Real browser verification, not just Pest — same Playwright +
      live-TOTP technique as Phase 2. This is genuinely where the
      value was: caught and fixed a real bug (d3.zoom() had no
      translateExtent, so panning could scroll into empty
      centuries-away void with nothing on screen — added
      .extent()/.translateExtent() clamped to the data's actual
      domain) that no amount of code review would have caught.
      Confirmed working end-to-end: initial zoomed-out render (dots,
      spans, decade gridlines, decluttered cards, avatars/emoji),
      pinch-zoom (ctrl+wheel) correctly rescaling and re-tiering
      gridlines down to month-level, the zoom meter driving the same
      transform, clamped panning (post-fix), dark mode styling, and
      the full click -> modal -> close flow on a story card. Test
      data cleaned up afterward.

## Status: all four phases complete.

Remaining follow-ups intentionally left for later (not blocking, not
forgotten):
- Card layout is a single row above the baseline — dense periods with
  many simultaneously-decluttered cards at very tight zoom could
  still overlap horizontally in rare cases; no vertical stacking
  implemented. Revisit if it comes up in real use.
- No automated JS test coverage for timeline.js/story-tagging.js (this
  repo has no JS test runner set up at all — verification for both
  was real-browser/Playwright, ad hoc, not checked into the suite).
  Business logic (TimelineSerializer, StoryBodyParser) is fully
  covered by Pest instead.
- `Person.dob_precision` still has no explicit UI control anywhere in
  the app outside of stories' new exact/year picker (noted in Phase 1
  — it's inferred, not chosen). Not in scope here, just flagging the
  inconsistency for whoever touches person dates next.

## Progress log

- **2026-09-17**: Plan drafted from initial feature request. Read
  existing `Story` model/views, confirmed Flux editor is TipTap-based
  with no bundled mention/image extensions and no known extensibility
  hook. Asked user clarifying questions; all decisions above are
  locked. No prod story data exists, so schema can require
  `start_date` outright. Nothing implemented yet — starting Phase 1
  next.
