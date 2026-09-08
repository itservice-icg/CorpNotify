using System.Net.Http.Headers;
using System.Net.Http.Json;
using System.Text.Json;
using CorpNotifyAgent.Models;

namespace CorpNotifyAgent;

internal sealed class ApiClient : IDisposable
{
    private readonly HttpClient _httpClient;
    private readonly JsonSerializerOptions _jsonOptions = new() { PropertyNameCaseInsensitive = true };

    public ApiClient(string apiUrl, string? apiToken = null)
    {
        _httpClient = new HttpClient
        {
            BaseAddress = new Uri(apiUrl.TrimEnd('/') + "/"),
            Timeout = TimeSpan.FromSeconds(15)
        };
        _httpClient.DefaultRequestHeaders.UserAgent.ParseAdd("CorpNotifyAgent/1.0");
        _httpClient.DefaultRequestHeaders.Accept.ParseAdd("application/json");
        SetToken(apiToken);
    }

    public void SetToken(string? token)
    {
        _httpClient.DefaultRequestHeaders.Authorization = string.IsNullOrWhiteSpace(token)
            ? null
            : new AuthenticationHeaderValue("Bearer", token);
    }

    public async Task<string?> RegisterAsync(DeviceRegistration registration, string? enrollmentKey, CancellationToken cancellationToken)
    {
        var body = new
        {
            device_uuid = registration.DeviceUuid,
            hostname = registration.Hostname,
            username = registration.Username,
            ip_address = registration.IpAddress,
            department = registration.Department,
            agent_version = registration.AgentVersion,
            enrollment_key = enrollmentKey
        };

        using var response = await _httpClient.PostAsJsonAsync("device/register", body, _jsonOptions, cancellationToken);
        response.EnsureSuccessStatusCode();
        using var document = JsonDocument.Parse(await response.Content.ReadAsStringAsync(cancellationToken));
        var token = document.RootElement.TryGetProperty("api_token", out var tokenElement)
            ? tokenElement.GetString()
            : null;
        SetToken(token);
        return token;
    }

    public Task HeartbeatAsync(DeviceRegistration registration, CancellationToken cancellationToken) =>
        PostAsync("device/heartbeat", registration, cancellationToken);

    public async Task<IReadOnlyList<Notification>> GetPendingAsync(string deviceUuid, CancellationToken cancellationToken)
    {
        var path = $"notifications/pending?device_uuid={Uri.EscapeDataString(deviceUuid)}";
        using var response = await _httpClient.GetAsync(path, cancellationToken);
        response.EnsureSuccessStatusCode();
        return await response.Content.ReadFromJsonAsync<List<Notification>>(_jsonOptions, cancellationToken) ?? [];
    }

    public Task MarkDeliveredAsync(long id, string deviceUuid, CancellationToken cancellationToken) =>
        PostStatusAsync(id, "delivered", deviceUuid, cancellationToken);

    public Task MarkOpenedAsync(long id, string deviceUuid, CancellationToken cancellationToken) =>
        PostStatusAsync(id, "opened", deviceUuid, cancellationToken);

    public Task MarkReadCompletedAsync(long id, string deviceUuid, CancellationToken cancellationToken) =>
        PostStatusAsync(id, "read-completed", deviceUuid, cancellationToken);

    public Task MarkQuizStartedAsync(long id, string deviceUuid, CancellationToken cancellationToken) =>
        PostStatusAsync(id, "quiz-started", deviceUuid, cancellationToken);

    public Task AcknowledgeAsync(long id, string deviceUuid, CancellationToken cancellationToken) =>
        PostStatusAsync(id, "acknowledge", deviceUuid, cancellationToken);

    public async Task<QuizSubmitResult> SubmitQuizAsync(
        long notificationId,
        string deviceUuid,
        IReadOnlyList<(long QuestionId, string Answer)> answers,
        CancellationToken cancellationToken)
    {
        var body = new
        {
            device_uuid = deviceUuid,
            answers = answers.Select(a => new { question_id = a.QuestionId, answer = a.Answer })
        };

        using var response = await _httpClient.PostAsJsonAsync($"notifications/{notificationId}/quiz", body, _jsonOptions, cancellationToken);
        return await response.Content.ReadFromJsonAsync<QuizSubmitResult>(_jsonOptions, cancellationToken)
               ?? new QuizSubmitResult();
    }

    private Task PostStatusAsync(long id, string action, string deviceUuid, CancellationToken cancellationToken) =>
        PostAsync($"notifications/{id}/{action}", new { device_uuid = deviceUuid }, cancellationToken);

    private async Task PostAsync<T>(string path, T body, CancellationToken cancellationToken)
    {
        using var response = await _httpClient.PostAsJsonAsync(path, body, _jsonOptions, cancellationToken);
        response.EnsureSuccessStatusCode();
    }

    public void Dispose() => _httpClient.Dispose();
}
