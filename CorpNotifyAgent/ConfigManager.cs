using System.Text.Json;

namespace CorpNotifyAgent;

internal static class ConfigManager
{
    private static readonly JsonSerializerOptions JsonOptions = new() { PropertyNameCaseInsensitive = true };

    public static async Task<AgentConfiguration> LoadAsync(CancellationToken cancellationToken)
    {
        var path = Path.Combine(AppContext.BaseDirectory, "appsettings.json");
        await using var stream = File.OpenRead(path);
        var configuration = await JsonSerializer.DeserializeAsync<AgentConfiguration>(stream, JsonOptions, cancellationToken)
            ?? throw new InvalidOperationException("appsettings.json is invalid.");

        if (!Uri.TryCreate(configuration.ApiUrl, UriKind.Absolute, out var uri) ||
            (uri.Scheme != Uri.UriSchemeHttps && !(uri.IsLoopback && uri.Scheme == Uri.UriSchemeHttp)))
        {
            throw new InvalidOperationException("ApiUrl must use HTTPS. HTTP is permitted only for localhost development.");
        }

        if (configuration.PollingIntervalSeconds < 5 || configuration.HeartbeatIntervalSeconds < 15)
        {
            throw new InvalidOperationException("Polling must be at least 5 seconds and heartbeat at least 15 seconds.");
        }

        return configuration;
    }
}
