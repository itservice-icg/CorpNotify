using System.Text.Json.Serialization;

namespace CorpNotifyAgent.Models;

internal sealed class DeviceRegistration
{
    [JsonPropertyName("device_uuid")]
    public required string DeviceUuid { get; init; }

    [JsonPropertyName("hostname")]
    public required string Hostname { get; init; }

    [JsonPropertyName("username")]
    public required string Username { get; init; }

    [JsonPropertyName("ip_address")]
    public string? IpAddress { get; init; }

    [JsonPropertyName("department")]
    public string? Department { get; init; }

    [JsonPropertyName("agent_version")]
    public required string AgentVersion { get; init; }
}
