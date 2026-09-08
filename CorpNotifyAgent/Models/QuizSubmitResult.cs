using System.Text.Json.Serialization;

namespace CorpNotifyAgent.Models;

internal sealed class QuizSubmitResult
{
    [JsonPropertyName("passed")]
    public bool Passed { get; init; }

    [JsonPropertyName("attempt_no")]
    public int AttemptNo { get; init; }

    [JsonPropertyName("incorrect_question_ids")]
    public List<long> IncorrectQuestionIds { get; init; } = [];
}
