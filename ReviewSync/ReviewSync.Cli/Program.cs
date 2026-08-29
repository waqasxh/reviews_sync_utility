using System.Net.Http.Headers;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Options;
using ReviewSync.Cli.Commands;
using ReviewSync.Cli.Logging;
using ReviewSync.Core.Fetching.GooglePlaces;
using ReviewSync.Storage;
using Serilog;

var configuration = new ConfigurationBuilder()
    .SetBasePath(AppContext.BaseDirectory)
    .AddJsonFile("appsettings.json", optional: false)
    .AddJsonFile("appsettings.local.json", optional: true)
    .AddEnvironmentVariables()
    // GOOGLE_PLACES_API_KEY is the documented override (README/CLAUDE.md); map it onto the
    // same config path appsettings.json uses so callers don't need to know the section name.
    .AddInMemoryCollection(BuildEnvKeyOverride())
    .Build();

Log.Logger = LoggerSetup.Create(configuration);

try
{
    var googlePlacesOptions = Options.Create(
        configuration.GetSection(GooglePlacesOptions.SectionName).Get<GooglePlacesOptions>()
        ?? new GooglePlacesOptions());

    using var httpClient = new HttpClient
    {
        Timeout = TimeSpan.FromSeconds(googlePlacesOptions.Value.TimeoutSeconds),
        DefaultRequestHeaders = { Accept = { new MediaTypeWithQualityHeaderValue("application/json") } },
    };

    var placesClient = new GooglePlacesClient(httpClient, googlePlacesOptions);
    var resolver = new GooglePlacesPlaceResolver(placesClient);
    var fetcher = new GooglePlacesFetcher(placesClient, resolver);

    var clientsFilePath = configuration["Storage:ClientsFilePath"] ?? "data/clients.json";
    var store = new ClientStore(clientsFilePath);

    if (args.Length == 0)
    {
        PrintUsage();
        return 1;
    }

    var command = args[0];
    var reader = new ArgReader(args.Skip(1));

    return command switch
    {
        "fetch" => await FetchCommand.RunAsync(reader, fetcher, store),
        "resolve" => await ResolveCommand.RunAsync(reader, resolver, store),
        "list-clients" => await ListClientsCommand.RunAsync(store),
        "--help" or "-h" or "help" => PrintUsageAndReturnZero(),
        _ => PrintUnknownCommand(command),
    };
}
catch (ArgumentException ex)
{
    Console.Error.WriteLine($"Argument error: {ex.Message}");
    return 1;
}
catch (Exception ex)
{
    Log.Error(ex, "Command failed");
    Console.Error.WriteLine($"Error: {ex.Message}");
    return 1;
}
finally
{
    Log.CloseAndFlush();
}

static Dictionary<string, string?> BuildEnvKeyOverride()
{
    var apiKey = Environment.GetEnvironmentVariable("GOOGLE_PLACES_API_KEY");
    return apiKey is null
        ? new Dictionary<string, string?>()
        : new Dictionary<string, string?> { [$"{GooglePlacesOptions.SectionName}:ApiKey"] = apiKey };
}

static int PrintUsageAndReturnZero()
{
    PrintUsage();
    return 0;
}

static int PrintUnknownCommand(string command)
{
    Console.Error.WriteLine($"Unknown command '{command}'.");
    PrintUsage();
    return 1;
}

static void PrintUsage()
{
    Console.WriteLine("""
        reviewsync - pull Google reviews for a client business

        Usage:
          reviewsync fetch --business "Shique London" [--location "West Kensington, London"]
                            [--tier free] [--limit 5] [--slug shique-london] [--out path.json] [--dry-run]
          reviewsync resolve --business "Shique London" [--location "..."] [--slug "..."]
          reviewsync list-clients

        Notes:
          - Only --tier free (Google Places, max 5 reviews) is implemented in this build.
            --tier paid (Outscraper) is deferred -- see CLAUDE.md "Build Status".
          - Set the Google Places API key via the GOOGLE_PLACES_API_KEY environment variable
            or appsettings.local.json (gitignored) -- never commit a real key to appsettings.json.
        """);
}
