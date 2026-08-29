using System.Security.Cryptography;
using System.Text;
using ReviewSync.Core.Fetching.GooglePlaces;
using ReviewSync.Core.Models;

namespace ReviewSync.Core.Normalization;

/// <summary>
/// Maps a raw Google Places PlaceDetailsResult into the unified ReviewExport schema.
/// Kept separate from GooglePlacesFetcher (which owns the HTTP call) so it can be unit
/// tested against fixed DTOs without hitting the network.
/// </summary>
public static class GooglePlacesReviewNormalizer
{
    public static ReviewExport Normalize(PlaceDetailsResult result, DateTimeOffset fetchedAt)
    {
        var business = new BusinessInfo
        {
            Name = result.Name,
            PlaceId = result.PlaceId,
            GoogleRating = result.Rating,
            GoogleReviewCount = result.UserRatingsTotal,
        };

        var reviews = result.Reviews
            .Select(r => NormalizeReview(r, result.PlaceId))
            .ToList();

        return new ReviewExport
        {
            Business = business,
            Source = "google_places",
            FetchedAt = fetchedAt,
            Reviews = reviews,
        };
    }

    private static ReviewItem NormalizeReview(GoogleReviewDto raw, string placeId)
    {
        // Google's legacy Places API supplies a unix timestamp directly, so unlike the
        // schema's suggested best-effort relative-time parsing, published_at here is exact.
        var publishedAt = raw.Time > 0
            ? DateTimeOffset.FromUnixTimeSeconds(raw.Time)
            : (DateTimeOffset?)null;

        return new ReviewItem
        {
            // The Places API never returns a review id, so derive a stable one: same
            // author + timestamp + text on a re-fetch always hashes to the same id,
            // which is what lets the WordPress importer upsert instead of duplicating.
            ReviewId = ComputeStableReviewId(placeId, raw.AuthorName, raw.Time, raw.Text),
            AuthorName = string.IsNullOrWhiteSpace(raw.AuthorName) ? "Anonymous" : raw.AuthorName,
            AuthorPhotoUrl = raw.ProfilePhotoUrl,
            Rating = raw.Rating,
            Text = raw.Text ?? string.Empty,
            RelativeTime = raw.RelativeTimeDescription,
            PublishedAt = publishedAt,
            Language = raw.Language,
            // Places API exposes no per-review deep link; point at the place's review list.
            SourceUrl = $"https://search.google.com/local/reviews?placeid={placeId}",
        };
    }

    public static string ComputeStableReviewId(string placeId, string authorName, long unixTime, string? text)
    {
        var payload = $"{placeId}|{authorName}|{unixTime}|{text}";
        var hash = SHA256.HashData(Encoding.UTF8.GetBytes(payload));
        return "gp_" + Convert.ToHexString(hash)[..20].ToLowerInvariant();
    }
}
