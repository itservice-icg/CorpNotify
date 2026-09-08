using System.Text.Json.Serialization;

namespace CorpNotifyAgent.Models;

internal sealed class Notification
{
    [JsonPropertyName("id")]
    public long Id { get; init; }

    [JsonPropertyName("title")]
    public string Title { get; init; } = string.Empty;

    [JsonPropertyName("message")]
    public string Message { get; init; } = string.Empty;

    [JsonPropertyName("image_url")]
    public string? ImageUrl { get; init; }

    [JsonPropertyName("image_base64")]
    public string? ImageBase64 { get; init; }

    [JsonPropertyName("type")]
    public string Type { get; init; } = "info";

    [JsonPropertyName("start_at")]
    public DateTimeOffset? StartAt { get; init; }

    [JsonPropertyName("was_opened")]
    public bool WasOpened { get; init; }

    [JsonPropertyName("url")]
    public string? Url { get; init; }

    [JsonPropertyName("policy_body")]
    public string? PolicyBody { get; init; }

    [JsonPropertyName("questions")]
    public List<QuizQuestion> Questions { get; init; } = [];

    public bool IsPolicy => string.Equals(Type, "policy", StringComparison.OrdinalIgnoreCase);
}


