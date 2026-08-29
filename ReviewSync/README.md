# ReviewSync (Console App)

Pulls Google reviews for a client business and writes them to a normalized JSON file for
the WordPress plugin to import. See the repo-root CLAUDE.md for the full system spec.

## Current build status

- Implemented: Tier 1 (free) via the Google Places API -- resolve a Place ID, fetch up to
  5 Google-selected reviews, normalize to the unified schema, cache the client.
- Deferred: Tier 2 (paid) via Outscraper. The --tier paid flag is recognized by the CLI and
  rejected with a clear message rather than silently doing nothing.
- Multi-tenant ready today: the client cache (data/clients.json) is a flat list keyed by
  slug, so tracking a second, third, ... business is just another entry -- no schema change
  needed when a real multi-tenant/agency use case shows up.

## Setup

    cd ReviewSync
    dotnet build

Set your Google Places API key via environment variable (preferred, never committed):

    PowerShell:  set GOOGLE_PLACES_API_KEY = your-key-here
    bash:        export GOOGLE_PLACES_API_KEY=your-key-here

Alternatively, copy ReviewSync.Cli/appsettings.json to ReviewSync.Cli/appsettings.local.json
(gitignored) and set GooglePlaces:ApiKey there.

## Usage

    cd ReviewSync.Cli

    dotnet run -- resolve --business "Shique London" --location "West Kensington, London"
    dotnet run -- fetch --business "Shique London" --dry-run
    dotnet run -- fetch --business "Shique London"
    dotnet run -- list-clients

- resolve looks up and caches a Place ID without fetching reviews.
- fetch resolves (if not already cached), pulls reviews, writes
  output/reviews-SLUG-TIMESTAMP.json, and updates the cache.
- --dry-run resolves the Place ID and reports what would be fetched without calling the
  (quota-billed) Place Details endpoint.
- --slug overrides the cache key derived from --business (useful when two clients share a
  name -- pair it with --location to disambiguate the underlying Place ID).

## Tests

    dotnet test ReviewSync.Core.Tests/ReviewSync.Core.Tests.csproj

Covers GooglePlacesReviewNormalizer -- the piece most likely to break silently since it is
the only place raw Google JSON becomes the schema the WordPress plugin depends on.

## Project layout

    ReviewSync.Core      IReviewFetcher/IPlaceResolver abstractions, GooglePlacesFetcher,
                          GooglePlacesReviewNormalizer, unified ReviewExport models
    ReviewSync.Storage    ClientStore (JSON-file client/place-id cache), ClientRecord, Slugify
    ReviewSync.Cli        Program.cs, per-command classes, appsettings.json, Serilog setup
    ReviewSync.Core.Tests Normalizer unit tests

## Adding Outscraper later (Tier 2)

IReviewFetcher and IPlaceResolver are the seams: add
ReviewSync.Core/Fetching/Outscraper/OutscraperFetcher.cs implementing IReviewFetcher with
SourceName returning "outscraper", handle its submit-then-poll job pattern internally, and
wire it into Program.cs behind --tier paid. No changes should be needed to
ReviewSync.Storage, the CLI argument surface, or the WordPress plugin.
