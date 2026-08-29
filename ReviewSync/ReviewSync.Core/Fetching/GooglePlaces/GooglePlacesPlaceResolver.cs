namespace ReviewSync.Core.Fetching.GooglePlaces;

public sealed class GooglePlacesPlaceResolver(GooglePlacesClient client) : IPlaceResolver
{
    public async Task<ResolvedPlace?> ResolveAsync(
        string businessName, string? locationBias, CancellationToken cancellationToken = default)
    {
        var response = await client.FindPlaceFromTextAsync(businessName, locationBias, cancellationToken);
        var candidate = response.Candidates.FirstOrDefault();
        return candidate is null
            ? null
            : new ResolvedPlace(candidate.PlaceId, candidate.Name, candidate.FormattedAddress);
    }
}
