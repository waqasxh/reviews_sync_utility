using System.Text.Json.Serialization;

namespace ReviewSync.Core.Fetching.GooglePlaces;

// Raw shapes for the legacy Google Places API (Find Place From Text + Place Details).
// These are intentionally 1:1 with Google's JSON so the normalizer has a stable, testable
// input to map from -- see GooglePlacesReviewNormalizer.

public sealed class FindPlaceResponse
{
    [JsonPropertyName("candidates")]
    public List<FindPlaceCandidate> Candidates { get; init; } = [];

    [JsonPropertyName("status")]
    public string Status { get; init; } = string.Empty;

    [JsonPropertyName("error_message")]
    public string? ErrorMessage { get; init; }
}

public sealed class FindPlaceCandidate
{
    [JsonPropertyName("place_id")]
    public string PlaceId { get; init; } = string.Empty;

    [JsonPropertyName("name")]
    public string Name { get; init; } = string.Empty;

    [JsonPropertyName("formatted_address")]
    public string? FormattedAddress { get; init; }
}

public sealed class PlaceDetailsResponse
{
    [JsonPropertyName("result")]
    public PlaceDetailsResult? Result { get; init; }

    [JsonPropertyName("status")]
    public string Status { get; init; } = string.Empty;

    [JsonPropertyName("error_message")]
    public string? ErrorMessage { get; init; }
}

public sealed class PlaceDetailsResult
{
    [JsonPropertyName("place_id")]
    public string PlaceId { get; init; } = string.Empty;

    [JsonPropertyName("name")]
    public string Name { get; init; } = string.Empty;

    [JsonPropertyName("rating")]
    public double? Rating { get; init; }

    [JsonPropertyName("user_ratings_total")]
    public int? UserRatingsTotal { get; init; }

    [JsonPropertyName("reviews")]
    public List<GoogleReviewDto> Reviews { get; init; } = [];
}

public sealed class GoogleReviewDto
{
    [JsonPropertyName("author_name")]
    public string AuthorName { get; init; } = string.Empty;

    [JsonPropertyName("author_url")]
    public string? AuthorUrl { get; init; }

    [JsonPropertyName("profile_photo_url")]
    public string? ProfilePhotoUrl { get; init; }

    [JsonPropertyName("rating")]
    public int Rating { get; init; }

    [JsonPropertyName("relative_time_description")]
    public string? RelativeTimeDescription { get; init; }

    [JsonPropertyName("text")]
    public string? Text { get; init; }

    [JsonPropertyName("language")]
    public string? Language { get; init; }

    /// <summary>Unix seconds. Google supplies this directly, so published_at never needs
    /// fuzzy-parsing relative_time_description for this source.</summary>
    [JsonPropertyName("time")]
    public long Time { get; init; }
}
