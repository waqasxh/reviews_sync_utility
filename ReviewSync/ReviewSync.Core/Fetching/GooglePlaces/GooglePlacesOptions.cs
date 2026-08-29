namespace ReviewSync.Core.Fetching.GooglePlaces;

/// <summary>
/// Bound from configuration (appsettings.json "GooglePlaces" section) with ApiKey normally
/// supplied via the GOOGLE_PLACES_API_KEY environment variable -- never commit a real key.
/// </summary>
public sealed class GooglePlacesOptions
{
    public const string SectionName = "GooglePlaces";

    public string ApiKey { get; set; } = string.Empty;
    public string BaseUrl { get; set; } = "https://maps.googleapis.com/maps/api/place";
    public int TimeoutSeconds { get; set; } = 20;
}
