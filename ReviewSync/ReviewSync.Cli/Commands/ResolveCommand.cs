using ReviewSync.Core.Fetching;
using ReviewSync.Storage;
using Serilog;

namespace ReviewSync.Cli.Commands;

public static class ResolveCommand
{
    public static async Task<int> RunAsync(ArgReader args, IPlaceResolver resolver, ClientStore store)
    {
        var business = args.GetRequiredString("business");
        var location = args.GetString("location");
        var slug = args.GetString("slug") ?? Slugify.From(business);

        Log.Information("Resolving place id for {Business} (location bias: {Location})", business, location ?? "(none)");

        var resolved = await resolver.ResolveAsync(business, location);
        if (resolved is null)
        {
            Log.Error("No Place ID found for {Business}", business);
            return 1;
        }

        await store.UpsertAsync(new ClientRecord
        {
            Slug = slug,
            BusinessName = resolved.Name,
            PlaceId = resolved.PlaceId,
            LocationBias = location,
            ResolvedAt = DateTimeOffset.UtcNow,
        });

        Console.WriteLine($"Resolved \"{resolved.Name}\" -> place_id={resolved.PlaceId} (cached as \"{slug}\")");
        if (resolved.FormattedAddress is not null)
            Console.WriteLine($"  Address: {resolved.FormattedAddress}");

        return 0;
    }
}
