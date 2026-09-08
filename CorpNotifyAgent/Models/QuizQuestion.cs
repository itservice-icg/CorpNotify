using System.Text.Json.Serialization;

namespace CorpNotifyAgent.Models;

internal sealed class QuizQuestion
{
    [JsonPropertyName("id")]
    public long Id { get; init; }

    [JsonPropertyName("question")]
    public string Question { get; init; } = string.Empty;
}
