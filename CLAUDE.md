# Review Sync System — Project Spec

## Overview

A two-part system for pulling Google Business reviews for client websites and displaying them on WordPress:

1. **Review Fetcher (Console App)** — a C#/.NET console application that takes a business name (or Google Place ID), pulls reviews from either the Google Places API (free tier, 5 reviews) or Outscraper (paid tier, unlimited/more reviews), and exports a normalized JSON file.
2. **WordPress Plugin** — a PHP plugin that ingests that JSON file, stores reviews in a custom database table, and renders them via shortcode/block with an admin UI for management.

The two components never talk to each other directly — they're decoupled by a JSON file contract. This keeps the console app hosting-agnostic and lets the plugin be dropped onto any client's WordPress site.

---

## Build Status (session continuity)

> This file is read automatically at the start of every Claude Code session in this
> directory, so this section is how work picks up across sessions without re-deriving
> context. Keep it current as work progresses — update it in the same turn you finish a
> milestone, not as an afterthought.

**Last updated:** 2026-08-30

### Console app (`/ReviewSync`) — in progress

- **Done:** Solution scaffolded (`ReviewSync.Core`, `ReviewSync.Storage`, `ReviewSync.Cli`,
  `ReviewSync.Core.Tests`), targeting net10.0. Builds clean, 8/8 unit tests passing.
- **Done:** Tier 1 (free) fully implemented — `GooglePlacesFetcher` + `GooglePlacesClient`
  hit the legacy Places `findplacefromtext` + `details` endpoints, capped at 5 reviews.
  `GooglePlacesReviewNormalizer` maps raw Google JSON to the unified schema and is unit
  tested (review-id hash stability, unix-time → `published_at`, blank-author fallback, etc.).
- **Done:** `IReviewFetcher` / `IPlaceResolver` abstractions in `ReviewSync.Core` are the
  seam for adding Outscraper later — no changes needed elsewhere when that lands.
- **Done:** `ClientStore` (`ReviewSync.Storage`) is a flat JSON list keyed by slug —
  multi-tenant is the default shape today, not a future migration.
- **Deferred (explicit decision):** Outscraper / Tier 2 (`--tier paid`). The CLI recognizes
  the flag and rejects it with a clear "not implemented yet" message rather than silently
  no-op'ing. See `ReviewSync/README.md` → "Adding Outscraper later" for the exact seam.
- **CLI commands implemented:** `fetch`, `resolve`, `list-clients` (hand-rolled `--key value`
  arg parsing in `ArgReader.cs` — `System.CommandLine`'s current preview API was too
  unstable to depend on, so the spec's suggested library was swapped out deliberately).
- **Discovery worth keeping:** Google's legacy Place Details API returns an exact unix
  timestamp (`time`) per review, not just a relative-time string — so `published_at` is
  always exact for this source; the schema's "best-effort relative-time parsing" fallback
  is only needed for a source that doesn't supply an epoch value.
- **Validated against a real API key** — `fetch --business "Logan Kingsley Solicitors"`
  ran end to end against the live Google Places API and produced
  `reviews-logan-kingsley-solicitors-20260830-052009.json`: 5 reviews, correct
  `place_id`/`google_rating`/`google_review_count`, exact `published_at` timestamps, stable
  `gp_`-prefixed review IDs, non-ASCII author names round-tripped correctly. Happy path
  confirmed working; no changes needed on the console-app side for now.

### WordPress plugin (`/client-reviews`) — live on the Logan Kingsley Solicitors site

- **Done:** Activation hook creates `wp_client_reviews` via `dbDelta()`, including
  `business_place_id` even in today's single-tenant usage per the spec's multi-tenant
  future-proofing note.
- **Done:** `Client_Reviews_Importer::import_from_file()` — schema_version major-version
  check, full validation before any DB write (no partial imports), upsert keyed on
  `review_id`. Re-imports never overwrite an admin's existing `is_visible`/`is_featured`
  choice on a review that already exists; only new rows get the default visibility.
- **Done 2026-08-30:** Importer also stores the export's top-level `business` block
  (`google_rating`, `google_review_count`, `place_id`, `name`) as options
  (`client_reviews_google_rating`, `client_reviews_google_review_count`,
  `client_reviews_business_place_id`, `client_reviews_business_name`) — needed because
  the real business total (Logan Kingsley: 4.9 average across 180 actual Google reviews)
  is never the same as an average computed from the small local sample the free Places
  tier returns (5 reviews, all coincidentally 5-star, which made an early homepage draft
  wrongly claim "Rated 5.0 out of 5 based on 5 reviews").
- **Done 2026-08-30:** New `[client_reviews_rating]` shortcode
  (`Client_Reviews_Shortcode::render_rating_summary()`) renders that real aggregate —
  "Rated 4.9 out of 5 based on 180 Google reviews", with the review count linking to
  the business's Google reviews page (`link="no"` attribute to disable). `Client_Reviews_
  Schema_Markup`'s JSON-LD now prefers the same stored options for `aggregateRating`
  over the local-sample computation too, for the same accuracy reason.
- **Done 2026-08-30:** Review card text (both grid and list templates) is now truncated
  to 150 characters on a word boundary (`Client_Reviews_Shortcode::excerpt()`, a class
  method rather than a bare template function specifically so it's safe if the shortcode
  ever renders twice on one page) with a "Read full review on Google" link to the
  review's real `source_url` — full-length reviews made cards inconsistent height, and
  the Google link lets a visitor verify the review is genuine and unedited.
- **Done:** Admin UI — All Reviews (`WP_List_Table`, sortable, AJAX visibility/featured
  toggles, per-row + bulk delete), Import (upload form + history from the
  `client_reviews_import_log` option), Settings (default visibility for new imports).
- **Done:** `[client_reviews limit min_rating layout]` shortcode and the
  `client-reviews/reviews` Gutenberg block share one render function
  (`Client_Reviews_Shortcode::render()`); the block previews live via `ServerSideRender`.
- **Resolved open decision (moderation default):** made it a Settings-page choice
  (`client_reviews_default_visibility` option, defaults to "visible") instead of hardcoding
  either way — applies only to newly inserted reviews, never touches existing ones.
- **Known gap:** `layout="carousel"` currently renders as grid with a `data-layout`
  attribute; no carousel JS is bundled yet.
- **Known gap, pre-existing, not caused by today's changes:** `assets/frontend.css` is
  never actually enqueued anywhere in the plugin (confirmed via grep 2026-08-30) — it's
  a dead file. Not an issue on the Logan Kingsley site today since that page provides its
  own scoped `<style>` block directly in the Elementor page content, but a future
  install on a site that doesn't do that would render completely unstyled. Worth wiring
  up `wp_enqueue_style` on `wp_enqueue_scripts` whenever the shortcode/block is actually
  present on a page, if this plugin gets used on another client site.
- **Deployed 2026-08-30**: installed+active on the actual Logan Kingsley Solicitors
  WordPress site (GoDaddy staging), not just reviewed by eye — activation, import (5 real
  Google reviews imported via `Client_Reviews_Importer::import_from_file()` run through
  `wp eval`), and the `[client_reviews]`/`[client_reviews_rating]` shortcode rendering
  were all exercised for real and verified via cache-busted curl (real reviewer names,
  star ratings, truncated text, "Read full review on Google" links, and the JSON-LD
  `aggregateRating` all confirmed correct: `{"ratingValue":4.9,"reviewCount":180}`). Files
  deployed straight over the live plugin directory
  (`wp-content/plugins/client-reviews/`), `php -l` checked on the server first for each
  changed file. See the Logan Kingsley project's own `CLAUDE.md` (in the LKS repo) for
  the page-side implementation details (the navy "Testimonials Section" on Home).

### Next steps (pick up here)

1. ~~Get a real Google Places API key, validate `resolve` → `fetch` end to end.~~ Done
   2026-08-30 — see above.
2. ~~Scaffold the WordPress plugin (`/client-reviews`) per the Part 2 spec below.~~ Done
   2026-08-30 — see above.
3. ~~Real WordPress smoke test (activate plugin, import the Logan Kingsley Solicitors
   JSON, confirm shortcode/block render, confirm JSON-LD validates).~~ Done 2026-08-30 —
   see "Deployed 2026-08-30" above. Live on the actual client site now, not just a test
   install.
4. When ready for Tier 2, implement `OutscraperFetcher : IReviewFetcher` per the seam
   described in `ReviewSync/README.md` — the free tier's 5-review cap is real (`total: 5`
   confirmed on every fetch so far), so this becomes relevant as soon as the client wants
   more than 5 individual reviews shown (the real 4.9/180 aggregate already displays
   correctly regardless, since that comes from the business block, not the review count).
5. Fix the dead `assets/frontend.css` enqueue gap noted above, if/when this plugin goes
   onto a second site that can't provide its own page-level `<style>` block.

---

## Architecture

```
[Console App] --pulls from--> [Google Places API | Outscraper API]
      |
      v
[reviews-{business-slug}-{timestamp}.json]
      |
      v  (uploaded via WP admin, or dropped in a watched folder / REST endpoint)
[WordPress Plugin] --parses & upserts--> [wp_client_reviews table]
      |
      v
[Admin UI: list/edit/hide/feature reviews] --renders--> [Shortcode / Gutenberg block on frontend]
```

Two data sources feed the same JSON schema. The plugin doesn't need to know or care which source a review came from — it just ingests conforming JSON. This means "upgrade a client to more reviews" is purely a console-app-side decision (call Outscraper instead of Places API); the plugin's ingestion logic never changes.

---

## Part 1: Console Application (C#/.NET)

### Responsibilities
- Accept a business name and location (or a stored Google Place ID) as input.
- Fetch reviews from one of two sources, selectable via a flag or config:
  - **Tier 1 (free):** Google Places API — `Place Details` endpoint, `fields=reviews,rating,user_ratings_total`. Returns max 5 reviews, Google-selected (not sortable/controllable).
  - **Tier 2 (paid):** Outscraper Google Maps Reviews API — returns configurable volume (e.g., 50, 100, all), with pagination/async job handling since Outscraper's API is often async (submit job → poll/webhook for results).
- Normalize both sources into one unified JSON schema (see below) so downstream consumption is identical regardless of source.
- Cache/store the resolved Place ID per business so re-runs don't require re-resolving it every time (store in a local SQLite DB or a simple `clients.json` lookup file, keyed by business slug).
- Write output to a JSON file named predictably, e.g. `reviews-{business-slug}-{yyyyMMdd-HHmmss}.json`.
- Log what was fetched (source, review count, timestamp, any API errors) to console and a rolling log file.
- Support a `--dry-run` mode that resolves the Place ID and shows what would be fetched without spending API quota.

### CLI shape (suggested)
```
reviewsync fetch --business "Shique London" --location "West Kensington, London" --tier free
reviewsync fetch --business "Shique London" --tier paid --limit 100
reviewsync resolve --business "Shique London"   # just resolves & caches Place ID
reviewsync list-clients                          # shows cached business -> place ID mappings
```

### Unified JSON schema (output contract)

```json
{
  "schema_version": "1.0",
  "business": {
    "name": "Shique London",
    "place_id": "ChIJ...",
    "google_rating": 4.8,
    "google_review_count": 142
  },
  "source": "google_places | outscraper",
  "fetched_at": "2026-08-30T12:00:00Z",
  "reviews": [
    {
      "review_id": "unique-stable-id",
      "author_name": "Jane D.",
      "author_photo_url": "https://...",
      "rating": 5,
      "text": "Great service...",
      "relative_time": "2 weeks ago",
      "published_at": "2026-08-14T00:00:00Z",
      "language": "en",
      "source_url": "https://www.google.com/maps/reviews/..."
    }
  ]
}
```

Notes for Claude Code:
- `review_id` needs to be **stable across re-fetches** so the plugin can upsert instead of duplicating. Google Places API doesn't give a clean unique ID per review — you'll likely need to derive one (hash of author_name + published_at + text) as a fallback when the source doesn't supply one. Outscraper typically supplies a more stable review ID — use it directly when present.
- `published_at` should be normalized to ISO 8601 even though Google Places API mostly gives relative time strings — do your best-effort parse of `relative_time`, and if you can't get an exact date, store null and keep `relative_time` for display fallback.
- Keep the schema additive/versioned (`schema_version`) so the plugin can handle older exports gracefully as the schema evolves.

### Config & secrets
- Google Places API key and Outscraper API key should be read from environment variables or a local `appsettings.json` / `.env` — **never committed to source control**.
- Since this will handle multiple clients, structure config as a per-client override on top of global defaults (e.g. some clients might need a specific `location` bias to avoid Place ID collisions with businesses of the same name elsewhere).

### Suggested project structure
```
/ReviewSync
  /ReviewSync.Cli        -- console entry point, command parsing
  /ReviewSync.Core        -- fetchers (GooglePlacesFetcher, OutscraperFetcher), normalization, models
  /ReviewSync.Storage     -- local client/place-id cache
  appsettings.json
  README.md
```

Ask Claude Code to:
1. Scaffold the solution with the structure above.
2. Implement `IReviewFetcher` interface with `GooglePlacesFetcher` and `OutscraperFetcher` implementations.
3. Implement the normalizer that maps each source's raw response to the unified schema.
4. Implement the CLI commands listed above using a lightweight arg-parsing library (e.g. `System.CommandLine`).
5. Handle Outscraper's async job pattern properly (submit → poll with backoff, or webhook receiver if you want it fully async) rather than assuming a synchronous response.
6. Write basic unit tests for the normalization logic specifically (that's the part most likely to break silently).

---

## Part 2: WordPress Plugin (PHP)

### Responsibilities
- Provide an admin screen to **upload a JSON export** (file upload field — no need for a watched folder unless you want to add SFTP/REST ingestion later).
- Parse the JSON, validate it against the expected schema, and **upsert** reviews into a custom table (insert new, update existing by `review_id`, never duplicate).
- Store reviews per-client if this plugin will ever be installed on a multi-site/agency dashboard, but for now assume **one site = one business**, so no client_id column is required unless you want to future-proof it (recommend adding a `business_place_id` column anyway even in single-tenant mode, so a future multi-tenant upgrade doesn't require a schema migration).
- Provide an admin UI (list table using WP's `WP_List_Table` class) to:
  - View all imported reviews
  - Toggle a review's visibility (show/hide on frontend)
  - Mark a review as "featured" (for pinning top testimonials)
  - Manually delete a review
  - See import history (when was the last JSON imported, how many reviews came in, from which source)
- Render reviews on the frontend via:
  - A shortcode: `[client_reviews limit="5" min_rating="4" layout="grid"]`
  - A Gutenberg block wrapping the same shortcode logic, with block editor controls for limit/rating filter/layout
- Include basic schema.org markup (`Review` / `AggregateRating` JSON-LD) in the rendered output so Google can pick up star ratings in search snippets — this matters for the client SEO work this feeds into.

### Suggested database table

```sql
CREATE TABLE {$wpdb->prefix}client_reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  review_id VARCHAR(191) NOT NULL,
  business_place_id VARCHAR(191) NULL,
  author_name VARCHAR(255),
  author_photo_url TEXT,
  rating TINYINT UNSIGNED,
  review_text TEXT,
  relative_time VARCHAR(100),
  published_at DATETIME NULL,
  language VARCHAR(10),
  source_url TEXT,
  source VARCHAR(50),
  is_visible TINYINT(1) DEFAULT 1,
  is_featured TINYINT(1) DEFAULT 0,
  imported_at DATETIME,
  UNIQUE KEY review_id_unique (review_id)
) {$charset_collate};
```

### Suggested plugin structure
```
/client-reviews
  client-reviews.php          -- plugin bootstrap, activation hook (create table)
  /includes
    class-importer.php        -- JSON parsing + validation + upsert logic
    class-admin-list-table.php -- WP_List_Table implementation
    class-admin-page.php      -- upload form, import history screen
    class-shortcode.php       -- [client_reviews] rendering
    class-block.php           -- Gutenberg block registration
    class-schema-markup.php   -- JSON-LD output
  /assets
    admin.css / admin.js
    frontend.css
  /templates
    review-grid.php
    review-list.php
```

Ask Claude Code to:
1. Scaffold the plugin skeleton with a proper activation hook that creates the table via `dbDelta()`.
2. Implement the JSON importer with:
   - Schema version check
   - Validation (reject malformed files with a clear admin-facing error, don't partially import)
   - Upsert logic keyed on `review_id`
   - An import log (could just be a WP option storing the last N import events, or a small `client_reviews_import_log` table if you want full history)
3. Build the admin list table with visibility/featured toggles (AJAX-driven, no page reload) and manual delete.
4. Build the shortcode with `limit`, `min_rating`, and `layout` (`grid`/`list`/`carousel`) attributes.
5. Build the Gutenberg block as a thin wrapper exposing the same attributes via block controls (use `render_callback` pointing at the same rendering function the shortcode uses — don't duplicate rendering logic).
6. Add the `Review`/`AggregateRating` JSON-LD schema block output alongside the visible markup.
7. Sanitize/escape everything on output (`esc_html`, `esc_url`, `wp_kses_post` for review text) — this is user-generated content from a public API, treat it as untrusted input.

---

## Tiering / upgrade path (business logic, not code)

- Every client starts on **Tier 1**: console app hits Google Places API, exports 5 reviews, JSON gets uploaded to their WordPress plugin.
- If a client pays for more reviews, you re-run the console app in **Tier 2** mode against Outscraper for the same business, producing a JSON with more reviews (and a `"source": "outscraper"` tag).
- Uploading the new JSON to the same plugin **upserts** — existing 5 reviews stay (matched by `review_id` if Outscraper happens to return overlapping ones) and new ones get added. No manual cleanup needed on the WordPress side.
- This means the plugin's importer must be source-agnostic and idempotent — re-importing the same file twice, or importing Tier 1 then Tier 2 data, should never create duplicates or corrupt state.

---

## Open decisions to flag back to the user (Waqas) before/while building

1. **JSON delivery mechanism**: manual upload via admin screen (simplest, recommended to start) vs. a REST endpoint the console app could push to directly (more automation, but requires auth/API-key handling on the WordPress side). Start with manual upload; design the importer as a standalone function so a REST endpoint can call it later without rewriting anything.
2. **Multi-tenant future**: if this plugin will eventually live on an agency dashboard managing many clients from one place, the schema/architecture should account for a `business_place_id`/client identifier now even though today it's one plugin install per client site.
3. **Outscraper's async pattern**: confirm expected turnaround (their reviews endpoint is often a submit-then-poll job, not instant) so the console app's UX (progress indicator, retry logic) accounts for that instead of assuming a fast synchronous call.
4. **Review moderation** — *resolved 2026-08-30*: rather than picking one default, this is now a Client Reviews > Settings toggle (`client_reviews_default_visibility`, defaults to "visible") that only affects newly inserted reviews. Waqas can flip it to "hidden" per-site if he wants to review new imports before they go live; either way, filtering is by visibility flag only — review text is never edited or fabricated.
