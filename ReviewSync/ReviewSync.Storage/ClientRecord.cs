using System.Text.Json.Serialization;

namespace ReviewSync.Storage;

/// <summary>
/// One cached business. The store keys many of these by Slug, so nothing here changes
/// when a second, third, ... client is added -- multi-tenant is the default shape, not a
/// future migration.
/// </summary>
public sealed class ClientRecord
{
    [JsonPropertyName("slug")]
    public required string Slug { get; init; }

    [JsonPropertyName("business_name")]
    public required string BusinessName { get; init; }

    [JsonPropertyName("place_id")]
    public required string PlaceId { get; init; }

    /// <summary>Location bias used at resolve time, kept so re-resolution reproduces the same match.</summary>
    [JsonPropertyName("location_bias")]
    public string? LocationBias { get; init; }

    /// <summary>Per-client override of the default tier ("free" today; "paid" once Outscraper lands).</summary>
    [JsonPropertyName("tier")]
    public string Tier { get; init; } = "free";

    [JsonPropertyName("resolved_at")]
    public DateTimeOffset ResolvedAt { get; init; }

    [JsonPropertyName("last_fetched_at")]
    public DateTimeOffset? LastFetchedAt { get; init; }

    [JsonPropertyName("last_fetch_review_count")]
    public int? LastFetchReviewCount { get; init; }
}
