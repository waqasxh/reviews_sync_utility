namespace ReviewSync.Core.Fetching;

public sealed record ResolvedPlace(string PlaceId, string Name, string? FormattedAddress);

/// <summary>
/// Resolves a business name (optionally biased by a location string) to a stable Google
/// Place ID. Shared by the `resolve` command and by fetchers that aren't given a cached
/// PlaceId already.
/// </summary>
public interface IPlaceResolver
{
    Task<ResolvedPlace?> ResolveAsync(string businessName, string? locationBias, CancellationToken cancellationToken = default);
}
