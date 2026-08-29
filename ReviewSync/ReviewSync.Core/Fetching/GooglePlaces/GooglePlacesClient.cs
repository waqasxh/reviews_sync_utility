using System.Net.Http.Json;
using System.Web;
using Microsoft.Extensions.Options;

namespace ReviewSync.Core.Fetching.GooglePlaces;

public sealed class GooglePlacesApiException(string message) : Exception(message);

/// <summary>Thin wrapper over the two legacy Places endpoints this app needs.</summary>
public sealed class GooglePlacesClient(HttpClient httpClient, IOptions<GooglePlacesOptions> options)
{
    private readonly GooglePlacesOptions _options = options.Value;

    public async Task<FindPlaceResponse> FindPlaceFromTextAsync(
        string query, string? locationBias, CancellationToken cancellationToken)
    {
        if (string.IsNullOrWhiteSpace(_options.ApiKey))
            throw new GooglePlacesApiException("GooglePlaces:ApiKey is not configured (set GOOGLE_PLACES_API_KEY).");

        var input = string.IsNullOrWhiteSpace(locationBias) ? query : $"{query} {locationBias}";
        var qs = HttpUtility.ParseQueryString(string.Empty);
        qs["input"] = input;
        qs["inputtype"] = "textquery";
        qs["fields"] = "place_id,name,formatted_address";
        qs["key"] = _options.ApiKey;

        var url = $"{_options.BaseUrl}/findplacefromtext/json?{qs}";
        var response = await httpClient.GetFromJsonAsync<FindPlaceResponse>(url, cancellationToken)
            ?? throw new GooglePlacesApiException("Find Place returned an empty response.");

        if (response.Status is not ("OK" or "ZERO_RESULTS"))
        {
            throw new GooglePlacesApiException(
                $"Find Place request failed with status {response.Status}: {response.ErrorMessage}");
        }

        return response;
    }

    public async Task<PlaceDetailsResponse> GetPlaceDetailsAsync(string placeId, CancellationToken cancellationToken)
    {
        if (string.IsNullOrWhiteSpace(_options.ApiKey))
            throw new GooglePlacesApiException("GooglePlaces:ApiKey is not configured (set GOOGLE_PLACES_API_KEY).");

        var qs = HttpUtility.ParseQueryString(string.Empty);
        qs["place_id"] = placeId;
        qs["fields"] = "place_id,name,rating,user_ratings_total,reviews";
        qs["key"] = _options.ApiKey;

        var url = $"{_options.BaseUrl}/details/json?{qs}";
        var response = await httpClient.GetFromJsonAsync<PlaceDetailsResponse>(url, cancellationToken)
            ?? throw new GooglePlacesApiException("Place Details returned an empty response.");

        if (response.Status != "OK")
        {
            throw new GooglePlacesApiException(
                $"Place Details request failed with status {response.Status}: {response.ErrorMessage}");
        }

        return response;
    }
}
