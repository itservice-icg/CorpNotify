namespace CorpNotifyAgent;

using System.Globalization;

internal sealed class AgentLogger
{
    private readonly string _logDirectory;
    private readonly SemaphoreSlim _lock = new(1, 1);

    public AgentLogger(string dataDirectory)
    {
        _logDirectory = Path.Combine(dataDirectory, "logs");
        Directory.CreateDirectory(_logDirectory);
    }

    public Task InfoAsync(string message) => WriteAsync("INFO", message);
    public Task ErrorAsync(string message) => WriteAsync("ERROR", message);

    private async Task WriteAsync(string level, string message)
    {
        var sanitized = message.ReplaceLineEndings(" ");
        var now = DateTimeOffset.Now;
        var timestamp = now.ToString("yyyy-MM-dd HH:mm:ss", CultureInfo.InvariantCulture);
        var date = now.ToString("yyyy-MM-dd", CultureInfo.InvariantCulture);
        var line = $"{timestamp} [{level}] {sanitized}{Environment.NewLine}";
        var path = Path.Combine(_logDirectory, $"agent-{date}.log");
        await _lock.WaitAsync();
        try { await File.AppendAllTextAsync(path, line); }
        finally { _lock.Release(); }
    }
}
