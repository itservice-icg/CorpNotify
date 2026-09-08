namespace CorpNotifyAgent;

internal sealed class AgentConfiguration
{
    public string ApiUrl { get; init; } = string.Empty;
    public int PollingIntervalSeconds { get; init; } = 15;
    public int HeartbeatIntervalSeconds { get; init; } = 60;
    public string? Department { get; init; }
    public string? EnrollmentKey { get; init; }
}
