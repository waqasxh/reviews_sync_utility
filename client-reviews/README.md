# Client Reviews (WordPress Plugin)

Imports the JSON exported by the ReviewSync console app into a wp_client_reviews table and
renders it via shortcode/block. See the repo-root CLAUDE.md for the full system spec.

## Current build status

- Implemented: activation hook creating wp_client_reviews via dbDelta; JSON importer with
  schema_version check, full validation before any write (no partial imports), and
  review_id-keyed upsert that preserves an admin's existing visible/featured choice on
  re-import.
- Implemented: admin screens -- All Reviews (WP_List_Table with sortable columns,
  AJAX visibility/featured toggles, per-row and bulk delete), Import (upload form + import
  history), Settings (default visibility for newly inserted reviews).
- Implemented: [client_reviews limit="5" min_rating="4" layout="grid"] shortcode with
  grid/list/carousel layouts (carousel currently reuses the grid template with a
  data-layout hook for a future JS carousel -- no carousel script is bundled yet).
  client-reviews/reviews Gutenberg block wraps the same render function via
  render_callback and previews live in the editor with ServerSideRender.
  Review/AggregateRating JSON-LD is emitted alongside the visible markup.
- Open decision resolved as a setting rather than hardcoded: newly imported reviews default
  to visible; change this under Client Reviews > Settings if you'd rather review new
  imports before they go live. Existing reviews are never affected by this setting.

## Install

Copy the client-reviews/ folder into wp-content/plugins/ on the target WordPress site and
activate it from Plugins. Activation creates the database table; no configuration is
required to start importing.

## Usage

1. Run the ReviewSync console app to produce a reviews-{slug}-{timestamp}.json file.
2. In WP Admin, go to Client Reviews > Import, upload that file.
3. Add the [client_reviews] shortcode (or the Client Reviews block) to any page/post.

## Project layout

    client-reviews.php                    Bootstrap: constants, activation hook (dbDelta),
                                           hook registration
    includes/class-importer.php           Schema validation + review_id upsert
    includes/class-admin-list-table.php   WP_List_Table for the All Reviews screen
    includes/class-admin-page.php         Menu, import screen, settings, AJAX handlers
    includes/class-shortcode.php          [client_reviews] -- the shared render function
    includes/class-block.php              Gutenberg block, delegates to the shortcode's render
    includes/class-schema-markup.php      Review/AggregateRating JSON-LD
    templates/review-grid.php             Grid layout template
    templates/review-list.php             List layout template
    assets/admin.css, admin.js            Admin screen styles + AJAX toggle/delete handlers
    assets/block.js                       Block editor controls (limit/minRating/layout)
    assets/frontend.css                   Frontend card/list styles

## Not yet built

- A real carousel script/library for layout="carousel" (currently falls back to the grid
  template).
- REST-endpoint ingestion (console app pushing directly instead of manual upload) -- the
  importer is already a standalone function (Client_Reviews_Importer::import_from_file),
  so a REST controller can call it without touching this logic.
- Automated tests (no PHP test runner is set up in this repo yet).
