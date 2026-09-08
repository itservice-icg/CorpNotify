using System.Text.Json.Serialization;

namespace CorpNotifyAgent.Models;

internal sealed class DeviceIdentity
{
    [JsonPropertyName("device_uuid")]
    public string DeviceUuid { get; set; } = string.Empty;

    [JsonPropertyName("api_token")]
    public string? ApiToken { get; set; }
}
