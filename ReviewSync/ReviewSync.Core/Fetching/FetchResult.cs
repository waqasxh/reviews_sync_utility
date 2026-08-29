using ReviewSync.Core.Models;

namespace ReviewSync.Core.Fetching;

/// <summary>
/// Output of an IReviewFetcher run. Export is null for a dry run, in which case
/// DryRunSummary describes what would have been fetched without spending API quota.
/// </summary>
public sealed class FetchResult
{
    public required string ResolvedPlaceId { get; init; }
    public required string ResolvedBusinessName { get; init; }
    public ReviewExport? Export { get; init; }
    public string? DryRunSummary { get; init; }
}
