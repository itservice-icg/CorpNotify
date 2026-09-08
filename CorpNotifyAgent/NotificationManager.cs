using CorpNotifyAgent.Models;

namespace CorpNotifyAgent;

internal sealed class NotificationManager
{
    private readonly ApiClient _apiClient;
    private readonly AgentLogger _logger;
    private readonly string _deviceUuid;
    private readonly HashSet<long> _displayed = [];
    private Form? _activeForm;

    public NotificationManager(ApiClient apiClient, AgentLogger logger, string deviceUuid)
    {
        _apiClient = apiClient;
        _logger = logger;
        _deviceUuid = deviceUuid;
    }

    public async Task PollAndDisplayAsync(CancellationToken cancellationToken)
    {
        if (_activeForm is not null && !_activeForm.IsDisposed) return;
        if (_policyFormActive) return;

        var notifications = await _apiClient.GetPendingAsync(_deviceUuid, cancellationToken);
        var newNotifications = notifications
            .Where(notification => _displayed.Add(notification.Id))
            .ToList();

        if (newNotifications.Count == 0) return;

        var policyNotifications = newNotifications.Where(n => n.IsPolicy).ToList();
        var regularNotifications = newNotifications.Where(n => !n.IsPolicy).ToList();

        try
        {
            foreach (var notification in newNotifications)
            {
                await _logger.InfoAsync($"Notification {notification.Id} received");
                await _apiClient.MarkDeliveredAsync(notification.Id, _deviceUuid, cancellationToken);
            }

            // Policy notifications are shown one at a time in their own locked-down
            // window, queued sequentially so the employee cannot skip one to reach another.
            // This does not block the poll loop: heartbeats keep firing while a
            // policy window is open, and the next queued one is chained via FormClosed.
            if (policyNotifications.Count > 0)
            {
                foreach (var notification in policyNotifications)
                {
                    _policyQueue.Enqueue(notification);
                }

                ShowNextPolicyFormIfIdle();
            }

            if (regularNotifications.Count > 0)
            {
                ShowRegularForm(regularNotifications);
            }
        }
        catch
        {
            foreach (var notification in newNotifications)
            {
                _displayed.Remove(notification.Id);
            }

            throw;
        }
    }

    private readonly Queue<Notification> _policyQueue = new();
    private bool _policyFormActive;

    private void ShowNextPolicyFormIfIdle()
    {
        if (_policyFormActive || _policyQueue.Count == 0) return;

        _policyFormActive = true;
        var notification = _policyQueue.Dequeue();
        ShowPolicyForm(notification);
    }

    private void ShowPolicyForm(Notification notification)
    {
        var form = new PolicyNotificationForm(
            notification,
            async n =>
            {
                await _apiClient.MarkOpenedAsync(n.Id, _deviceUuid, CancellationToken.None);
                await _logger.InfoAsync($"Notification {n.Id} opened");
            },
            async n =>
            {
                await _apiClient.MarkReadCompletedAsync(n.Id, _deviceUuid, CancellationToken.None);
                await _logger.InfoAsync($"Notification {n.Id} policy read completed");
            },
            async n =>
            {
                await _apiClient.MarkQuizStartedAsync(n.Id, _deviceUuid, CancellationToken.None);
                await _logger.InfoAsync($"Notification {n.Id} quiz started");
            },
            async (n, answers) =>
            {
                var result = await _apiClient.SubmitQuizAsync(n.Id, _deviceUuid, answers, CancellationToken.None);
                await _logger.InfoAsync($"Notification {n.Id} quiz submitted, passed={result.Passed}");
                return result;
            },
            async n =>
            {
                await _apiClient.AcknowledgeAsync(n.Id, _deviceUuid, CancellationToken.None);
                _displayed.Remove(n.Id);
                await _logger.InfoAsync($"Notification {n.Id} acknowledged");
            });

        _activeForm = form;
        form.FormClosed += (_, _) =>
        {
            _activeForm = null;
            _policyFormActive = false;
            ShowNextPolicyFormIfIdle();
        };
        form.Show();
    }

    private void ShowRegularForm(List<Notification> regularNotifications)
    {
        var form = new NotificationForm(
            regularNotifications,
            async notification =>
            {
                await _apiClient.MarkOpenedAsync(notification.Id, _deviceUuid, CancellationToken.None);
                await _logger.InfoAsync($"Notification {notification.Id} opened");
            },
            async notification =>
            {
                await _apiClient.AcknowledgeAsync(notification.Id, _deviceUuid, CancellationToken.None);
                _displayed.Remove(notification.Id);
                await _logger.InfoAsync($"Notification {notification.Id} acknowledged");
            });

        _activeForm = form;
        form.FormClosed += (_, _) => _activeForm = null;
        form.Show();

        foreach (var notification in regularNotifications)
        {
            _logger.InfoAsync($"Notification {notification.Id} displayed");
        }
    }
}
