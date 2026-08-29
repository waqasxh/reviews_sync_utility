using ReviewSync.Core.Normalization;

namespace ReviewSync.Core.Fetching.GooglePlaces;

/// <summary>Tier 1 (free) fetcher: Google Places Details, capped at 5 Google-selected reviews.</summary>
public sealed class GooglePlacesFetcher(GooglePlacesClient client, IPlaceResolver resolver) : IReviewFetcher
{
    public const int MaxReviews = 5;

    public string SourceName => "google_places";

    public async Task<FetchResult> FetchAsync(FetchRequest request, CancellationToken cancellationToken = default)
    {
        if (request.Limit is > MaxReviews)
        {
            throw new InvalidOperationException(
                $"--limit {request.Limit} exceeds what the free Google Places tier can return " +
                $"(max {MaxReviews}). Use --tier paid once Outscraper support lands.");
        }

        var (placeId, resolvedName) = await ResolvePlaceAsync(request, cancellationToken);

        if (request.DryRun)
        {
            return new FetchResult
            {
                ResolvedPlaceId = placeId,
                ResolvedBusinessName = resolvedName,
                Export = null,
                DryRunSummary =
                    $"Would fetch up to {MaxReviews} Google-selected reviews for \"{resolvedName}\" " +
                    $"(place_id={placeId}) via the Places Details API. No Details API quota spent.",
            };
        }

        var details = await client.GetPlaceDetailsAsync(placeId, cancellationToken);
        if (details.Result is null)
            throw new GooglePlacesApiException($"Place Details returned no result for place_id {placeId}.");

        var export = GooglePlacesReviewNormalizer.Normalize(details.Result, DateTimeOffset.UtcNow);

        return new FetchResult
        {
            ResolvedPlaceId = placeId,
            ResolvedBusinessName = details.Result.Name,
            Export = export,
        };
    }

    private async Task<(string PlaceId, string Name)> ResolvePlaceAsync(
        FetchRequest request, CancellationToken cancellationToken)
    {
        if (!string.IsNullOrWhiteSpace(request.PlaceId))
            return (request.PlaceId, request.BusinessName);

        var resolved = await resolver.ResolveAsync(request.BusinessName, request.LocationBias, cancellationToken)
            ?? throw new InvalidOperationException(
                $"Could not resolve a Place ID for \"{request.BusinessName}\"" +
                (request.LocationBias is null ? "." : $" near \"{request.LocationBias}\"."));

        return (resolved.PlaceId, resolved.Name);
    }
}
