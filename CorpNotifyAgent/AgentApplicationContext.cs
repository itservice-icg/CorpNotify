using CorpNotifyAgent.Models;

namespace CorpNotifyAgent;

internal sealed class AgentApplicationContext : ApplicationContext
{
    private readonly AgentConfiguration _configuration;
    private readonly AgentLogger _logger;
    private readonly ApiClient _apiClient;
    private readonly DeviceRegistration _registration;
    private readonly DeviceManager _deviceManager;
    private readonly DeviceIdentity _identity;
    private readonly NotificationManager _notificationManager;
    private readonly CancellationTokenSource _shutdown = new();
    private readonly NotifyIcon _trayIcon;
    private readonly System.Windows.Forms.Timer _pollTimer;
    private readonly Form _hostForm;
    private DateTimeOffset _nextHeartbeat = DateTimeOffset.MinValue;
    private bool _busy;

    public AgentApplicationContext(AgentConfiguration configuration, AgentLogger logger, ApiClient apiClient, DeviceRegistration registration, DeviceManager deviceManager, DeviceIdentity identity)
    {
        _configuration = configuration;
        _logger = logger;
        _apiClient = apiClient;
        _registration = registration;
        _deviceManager = deviceManager;
        _identity = identity;
        _notificationManager = new NotificationManager(apiClient, logger, registration.DeviceUuid);

        _hostForm = new Form
        {
            ShowInTaskbar = false,
            FormBorderStyle = FormBorderStyle.None,
            WindowState = FormWindowState.Minimized,
            Opacity = 0
        };
        _hostForm.Shown += HostFormShown;
        MainForm = _hostForm;

        var menu = new ContextMenuStrip();
        menu.Items.Add("ออก", null, (_, _) => ExitThread());
        _trayIcon = new NotifyIcon
        {
            Icon = SystemIcons.Information,
            Text = "CorpNotify Agent",
            Visible = true,
            ContextMenuStrip = menu
        };

        _pollTimer = new System.Windows.Forms.Timer { Interval = configuration.PollingIntervalSeconds * 1000 };
        _pollTimer.Tick += PollTimerTick;
    }

    private async void HostFormShown(object? sender, EventArgs e)
    {
        _hostForm.Shown -= HostFormShown;
        _hostForm.Hide();
        await _logger.InfoAsync("Agent started");
        await TryApiActionAsync("Device registration", RegisterDeviceAsync);
        await PollAsync();
        _pollTimer.Start();
    }

    private async Task RegisterDeviceAsync()
    {
        var token = await _apiClient.RegisterAsync(_registration, _configuration.EnrollmentKey, _shutdown.Token);
        if (!string.IsNullOrWhiteSpace(token))
        {
            _identity.ApiToken = token;
            await _deviceManager.SaveAsync(_identity, _shutdown.Token);
        }
    }

    private async void PollTimerTick(object? sender, EventArgs e) => await PollAsync();

    private async Task PollAsync()
    {
        if (_busy || _shutdown.IsCancellationRequested) return;
        _busy = true;
        try
        {
            if (DateTimeOffset.UtcNow >= _nextHeartbeat)
            {
                await TryApiActionAsync("Heartbeat", () => _apiClient.HeartbeatAsync(_registration, _shutdown.Token));
                _nextHeartbeat = DateTimeOffset.UtcNow.AddSeconds(_configuration.HeartbeatIntervalSeconds);
            }

            await TryApiActionAsync("Notification poll", () => _notificationManager.PollAndDisplayAsync(_shutdown.Token));
        }
        finally { _busy = false; }
    }

    private async Task TryApiActionAsync(string action, Func<Task> operation)
    {
        try
        {
            await operation();
            if (action == "Device registration") await _logger.InfoAsync("Device registered");
        }
        catch (OperationCanceledException) when (_shutdown.IsCancellationRequested) { }
        catch (Exception exception)
        {
            await _logger.ErrorAsync($"{action} failed: {exception.GetType().Name} - {exception.Message}");
        }
    }

    protected override void ExitThreadCore()
    {
        _shutdown.Cancel();
        _pollTimer.Stop();
        _pollTimer.Dispose();
        _trayIcon.Visible = false;
        _trayIcon.Dispose();
        _apiClient.Dispose();
        _hostForm.Dispose();
        base.ExitThreadCore();
    }
}
