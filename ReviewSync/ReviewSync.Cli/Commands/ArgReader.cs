namespace ReviewSync.Cli.Commands;

/// <summary>Minimal `--key value` / `--flag` reader. The CLI surface here is small enough
/// (three subcommands, a handful of options each) that a full parsing library would be
/// more ceremony than the problem needs.</summary>
public sealed class ArgReader
{
    private readonly Dictionary<string, string?> _options = new(StringComparer.OrdinalIgnoreCase);

    public ArgReader(IEnumerable<string> args)
    {
        var list = args.ToList();
        for (var i = 0; i < list.Count; i++)
        {
            var token = list[i];
            if (!token.StartsWith("--", StringComparison.Ordinal))
                continue;

            var key = token[2..];
            var hasValue = i + 1 < list.Count && !list[i + 1].StartsWith("--", StringComparison.Ordinal);
            _options[key] = hasValue ? list[++i] : null;
        }
    }

    public string? GetString(string key) => _options.TryGetValue(key, out var v) ? v : null;

    public string GetRequiredString(string key) =>
        GetString(key) ?? throw new ArgumentException($"Missing required option --{key}");

    public bool GetFlag(string key) => _options.ContainsKey(key);

    public int? GetInt(string key)
    {
        var raw = GetString(key);
        if (raw is null) return null;
        return int.TryParse(raw, out var value)
            ? value
            : throw new ArgumentException($"--{key} must be an integer, got '{raw}'");
    }
}
