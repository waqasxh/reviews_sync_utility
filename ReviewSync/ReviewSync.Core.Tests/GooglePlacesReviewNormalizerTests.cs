using ReviewSync.Core.Fetching.GooglePlaces;
using ReviewSync.Core.Normalization;
using Xunit;

namespace ReviewSync.Core.Tests;

public class GooglePlacesReviewNormalizerTests
{
    private static PlaceDetailsResult SampleResult(params GoogleReviewDto[] reviews) => new()
    {
        PlaceId = "ChIJexample123",
        Name = "Shique London",
        Rating = 4.8,
        UserRatingsTotal = 142,
        Reviews = reviews.ToList(),
    };

    private static GoogleReviewDto SampleReview(
        string authorName = "Jane D.",
        long time = 1_691_020_800, // 2023-08-03T00:00:00Z
        string? text = "Great service, highly recommend.",
        int rating = 5) => new()
    {
        AuthorName = authorName,
        Rating = rating,
        Text = text,
        Time = time,
        RelativeTimeDescription = "2 weeks ago",
        Language = "en",
        ProfilePhotoUrl = "https://example.com/photo.jpg",
    };

    [Fact]
    public void Normalize_MapsBusinessFieldsFromResult()
    {
        var export = GooglePlacesReviewNormalizer.Normalize(SampleResult(), DateTimeOffset.UtcNow);

        Assert.Equal("Shique London", export.Business.Name);
        Assert.Equal("ChIJexample123", export.Business.PlaceId);
        Assert.Equal(4.8, export.Business.GoogleRating);
        Assert.Equal(142, export.Business.GoogleReviewCount);
        Assert.Equal("google_places", export.Source);
    }

    [Fact]
    public void Normalize_ConvertsUnixTimeToExactPublishedAt()
    {
        var review = SampleReview(time: 1_691_020_800);
        var export = GooglePlacesReviewNormalizer.Normalize(SampleResult(review), DateTimeOffset.UtcNow);

        var published = Assert.Single(export.Reviews).PublishedAt;
        Assert.Equal(DateTimeOffset.FromUnixTimeSeconds(1_691_020_800), published);
    }

    [Fact]
    public void Normalize_ReviewIdIsStableAcrossRepeatedNormalization()
    {
        var review = SampleReview();

        var first = GooglePlacesReviewNormalizer.Normalize(SampleResult(review), DateTimeOffset.UtcNow);
        var second = GooglePlacesReviewNormalizer.Normalize(SampleResult(review), DateTimeOffset.UtcNow.AddDays(1));

        Assert.Equal(
            Assert.Single(first.Reviews).ReviewId,
            Assert.Single(second.Reviews).ReviewId);
    }

    [Fact]
    public void Normalize_ReviewIdChangesWhenReviewContentDiffers()
    {
        var reviewA = SampleReview(authorName: "Jane D.");
        var reviewB = SampleReview(authorName: "John S.");

        var exportA = GooglePlacesReviewNormalizer.Normalize(SampleResult(reviewA), DateTimeOffset.UtcNow);
        var exportB = GooglePlacesReviewNormalizer.Normalize(SampleResult(reviewB), DateTimeOffset.UtcNow);

        Assert.NotEqual(
            Assert.Single(exportA.Reviews).ReviewId,
            Assert.Single(exportB.Reviews).ReviewId);
    }

    [Fact]
    public void Normalize_MissingTimeYieldsNullPublishedAtButKeepsRelativeTime()
    {
        var review = SampleReview(time: 0);
        var export = GooglePlacesReviewNormalizer.Normalize(SampleResult(review), DateTimeOffset.UtcNow);

        var normalized = Assert.Single(export.Reviews);
        Assert.Null(normalized.PublishedAt);
        Assert.Equal("2 weeks ago", normalized.RelativeTime);
    }

    [Fact]
    public void Normalize_BlankAuthorNameFallsBackToAnonymous()
    {
        var review = SampleReview(authorName: "  ");
        var export = GooglePlacesReviewNormalizer.Normalize(SampleResult(review), DateTimeOffset.UtcNow);

        Assert.Equal("Anonymous", Assert.Single(export.Reviews).AuthorName);
    }

    [Fact]
    public void Normalize_NullReviewTextBecomesEmptyString()
    {
        var review = SampleReview(text: null);
        var export = GooglePlacesReviewNormalizer.Normalize(SampleResult(review), DateTimeOffset.UtcNow);

        Assert.Equal(string.Empty, Assert.Single(export.Reviews).Text);
    }

    [Fact]
    public void Normalize_HandlesZeroReviews()
    {
        var export = GooglePlacesReviewNormalizer.Normalize(SampleResult(), DateTimeOffset.UtcNow);
        Assert.Empty(export.Reviews);
    }
}
