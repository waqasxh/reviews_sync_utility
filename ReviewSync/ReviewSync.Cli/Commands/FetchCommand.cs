using System.Text.Json;
using ReviewSync.Core.Fetching;
using ReviewSync.Storage;
using Serilog;

namespace ReviewSync.Cli.Commands;

public static class FetchCommand
{
    private static readonly JsonSerializerOptions ExportJsonOptions = new() { WriteIndented = true };

    public static async Task<int> RunAsync(ArgReader args, IReviewFetcher fetcher, ClientStore store)
    {
        var business = args.GetRequiredString("business");
        var slug = args.GetString("slug") ?? Slugify.From(business);
        var tier = args.GetString("tier") ?? "free";

        if (!string.Equals(tier, "free", StringComparison.OrdinalIgnoreCase))
        {
            Console.Error.WriteLine(
                $"--tier {tier} is not implemented yet. This build only supports the free " +
                "Google Places tier; Outscraper (--tier paid) is deferred to a later session " +
                "(see CLAUDE.md 'Build Status').");
            return 1;
        }

        var cached = await store.GetAsync(slug);
        var location = args.GetString("location") ?? cached?.LocationBias;
        var placeId = args.GetString("place-id") ?? cached?.PlaceId;
        var dryRun = args.GetFlag("dry-run");

        var request = new FetchRequest
        {
            BusinessName = business,
            LocationBias = location,
            PlaceId = placeId,
            Limit = args.GetInt("limit"),
            DryRun = dryRun,
        };

        Log.Information(
            "Fetching reviews for {Business} (source={Source}, dry-run={DryRun}, cached place_id={PlaceId})",
            business, fetcher.SourceName, dryRun, placeId ?? "(none)");

        var result = await fetcher.FetchAsync(request);

        await store.UpsertAsync(new ClientRecord
        {
            Slug = slug,
            BusinessName = result.ResolvedBusinessName,
            PlaceId = result.ResolvedPlaceId,
            LocationBias = location,
            Tier = tier,
            ResolvedAt = cached?.ResolvedAt ?? DateTimeOffset.UtcNow,
            LastFetchedAt = result.Export is null ? cached?.LastFetchedAt : DateTimeOffset.UtcNow,
            LastFetchReviewCount = result.Export?.Reviews.Count ?? cached?.LastFetchReviewCount,
        });

        if (result.Export is null)
        {
            Console.WriteLine(result.DryRunSummary);
            return 0;
        }

        var outPath = args.GetString("out")
            ?? Path.Combine("output", $"reviews-{slug}-{DateTimeOffset.UtcNow:yyyyMMdd-HHmmss}.json");

        var directory = Path.GetDirectoryName(outPath);
        if (!string.IsNullOrEmpty(directory))
            Directory.CreateDirectory(directory);

        await using (var stream = File.Create(outPath))
        {
            await JsonSerializer.SerializeAsync(stream, result.Export, ExportJsonOptions);
        }

        Log.Information(
            "Fetched {Count} reviews for {Business} from {Source}, wrote {OutPath}",
            result.Export.Reviews.Count, result.ResolvedBusinessName, result.Export.Source, outPath);

        Console.WriteLine($"Wrote {result.Export.Reviews.Count} reviews to {outPath}");
        return 0;
    }
}
