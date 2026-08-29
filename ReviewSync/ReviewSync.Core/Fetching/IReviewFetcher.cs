namespace ReviewSync.Core.Fetching;

/// <summary>
/// A source of reviews (Google Places, Outscraper, ...). Every implementation normalizes
/// into the same ReviewExport shape so the CLI and downstream WordPress plugin never need
/// to know which source produced a given JSON file.
/// </summary>
public interface IReviewFetcher
{
    /// <summary>Value written to ReviewExport.Source, e.g. "google_places" or "outscraper".</summary>
    string SourceName { get; }

    Task<FetchResult> FetchAsync(FetchRequest request, CancellationToken cancellationToken = default);
}
