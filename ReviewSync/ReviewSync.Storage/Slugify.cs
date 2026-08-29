using System.Text;

namespace ReviewSync.Storage;

public static class Slugify
{
    /// <summary>"Shique London" -> "shique-london". Used as the default client key so
    /// `fetch --business "..."` and `resolve --business "..."` address the same cache entry
    /// without the caller having to track an id.</summary>
    public static string From(string businessName)
    {
        var sb = new StringBuilder(businessName.Length);
        var lastWasDash = false;

        foreach (var ch in businessName.Trim().ToLowerInvariant())
        {
            if (char.IsLetterOrDigit(ch))
            {
                sb.Append(ch);
                lastWasDash = false;
            }
            else if (!lastWasDash && sb.Length > 0)
            {
                sb.Append('-');
                lastWasDash = true;
            }
        }

        if (sb.Length > 0 && sb[^1] == '-')
            sb.Length--;

        return sb.Length == 0 ? "client" : sb.ToString();
    }
}
