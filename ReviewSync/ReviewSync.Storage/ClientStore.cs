using System.Text.Json;

namespace ReviewSync.Storage;

/// <summary>
/// JSON-file-backed cache of resolved clients (business -> Place ID), keyed by slug.
/// One flat file holding every client is deliberately the multi-tenant shape: adding the
/// 2nd, 10th, or 50th business is just another entry, not a schema change.
/// </summary>
public sealed class ClientStore(string filePath)
{
    private static readonly JsonSerializerOptions JsonOptions = new() { WriteIndented = true };

    public async Task<IReadOnlyList<ClientRecord>> ListAsync(CancellationToken cancellationToken = default)
    {
        if (!File.Exists(filePath))
            return [];

        await using var stream = File.OpenRead(filePath);
        var records = await JsonSerializer.DeserializeAsync<List<ClientRecord>>(stream, JsonOptions, cancellationToken);
        return records ?? [];
    }

    public async Task<ClientRecord?> GetAsync(string slug, CancellationToken cancellationToken = default)
    {
        var all = await ListAsync(cancellationToken);
        return all.FirstOrDefault(c => string.Equals(c.Slug, slug, StringComparison.OrdinalIgnoreCase));
    }

    /// <summary>Insert or replace the record for this slug, then persist the whole file.</summary>
    public async Task UpsertAsync(ClientRecord record, CancellationToken cancellationToken = default)
    {
        var all = (await ListAsync(cancellationToken)).ToList();
        var index = all.FindIndex(c => string.Equals(c.Slug, record.Slug, StringComparison.OrdinalIgnoreCase));

        if (index >= 0)
            all[index] = record;
        else
            all.Add(record);

        var directory = Path.GetDirectoryName(filePath);
        if (!string.IsNullOrEmpty(directory))
            Directory.CreateDirectory(directory);

        await using var stream = File.Create(filePath);
        await JsonSerializer.SerializeAsync(stream, all, JsonOptions, cancellationToken);
    }
}
