using System.Text.Json.Serialization;

namespace ReviewSync.Core.Models;

/// <summary>
/// Root object written to the output JSON file. This is the contract consumed by the
/// WordPress plugin's importer -- keep it additive across schema versions.
/// </summary>
public sealed class ReviewExport
{
    [JsonPropertyName("schema_version")]
    public string SchemaVersion { get; init; } = "1.0";

    [JsonPropertyName("business")]
    public required BusinessInfo Business { get; init; }

    [JsonPropertyName("source")]
    public required string Source { get; init; }

    [JsonPropertyName("fetched_at")]
    public required DateTimeOffset FetchedAt { get; init; }

    [JsonPropertyName("reviews")]
    public required IReadOnlyList<ReviewItem> Reviews { get; init; }
}

public sealed class BusinessInfo
{
    [JsonPropertyName("name")]
    public required string Name { get; init; }

    [JsonPropertyName("place_id")]
    public required string PlaceId { get; init; }

    [JsonPropertyName("google_rating")]
    public double? GoogleRating { get; init; }

    [JsonPropertyName("google_review_count")]
    public int? GoogleReviewCount { get; init; }
}

public sealed class ReviewItem
{
    [JsonPropertyName("review_id")]
    public required string ReviewId { get; init; }

    [JsonPropertyName("author_name")]
    public required string AuthorName { get; init; }

    [JsonPropertyName("author_photo_url")]
    public string? AuthorPhotoUrl { get; init; }

    [JsonPropertyName("rating")]
    public required int Rating { get; init; }

    [JsonPropertyName("text")]
    public string Text { get; init; } = string.Empty;

    [JsonPropertyName("relative_time")]
    public string? RelativeTime { get; init; }

    [JsonPropertyName("published_at")]
    public DateTimeOffset? PublishedAt { get; init; }

    [JsonPropertyName("language")]
    public string? Language { get; init; }

    [JsonPropertyName("source_url")]
    public string? SourceUrl { get; init; }
}
