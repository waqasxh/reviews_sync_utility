using ReviewSync.Storage;

namespace ReviewSync.Cli.Commands;

public static class ListClientsCommand
{
    public static async Task<int> RunAsync(ClientStore store)
    {
        var clients = await store.ListAsync();
        if (clients.Count == 0)
        {
            Console.WriteLine("No cached clients yet. Run `reviewsync resolve --business \"...\"` first.");
            return 0;
        }

        Console.WriteLine($"{"SLUG",-25} {"BUSINESS",-30} {"TIER",-6} {"PLACE ID",-30} LAST FETCH");
        foreach (var c in clients)
        {
            var lastFetch = c.LastFetchedAt is { } t
                ? $"{t:yyyy-MM-dd HH:mm} UTC ({c.LastFetchReviewCount} reviews)"
                : "never";
            Console.WriteLine($"{c.Slug,-25} {c.BusinessName,-30} {c.Tier,-6} {c.PlaceId,-30} {lastFetch}");
        }

        return 0;
    }
}
