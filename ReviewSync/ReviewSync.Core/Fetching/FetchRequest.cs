namespace ReviewSync.Core.Fetching;

/// <summary>
/// Input to an IReviewFetcher. PlaceId, when already known (e.g. from the client cache),
/// lets a fetcher skip re-resolving it.
/// </summary>
public sealed class FetchRequest
{
    public required string BusinessName { get; init; }
    public string? LocationBias { get; init; }
    public string? PlaceId { get; init; }
    public int? Limit { get; init; }
    public bool DryRun { get; init; }
}
