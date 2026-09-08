using CorpNotifyAgent.Models;

namespace CorpNotifyAgent;

internal sealed class NotificationForm : Form
{
    private static readonly Color PageBackground = Color.FromArgb(246, 248, 251);
    private static readonly Color Ink = Color.FromArgb(15, 23, 42);
    private static readonly Color MutedInk = Color.FromArgb(100, 116, 139);
    private static readonly Color Border = Color.FromArgb(226, 232, 240);

    private readonly IReadOnlyList<Notification> _notifications;
    private readonly Func<Notification, Task> _onOpen;
    private readonly Func<Notification, Task> _onAcknowledge;
    private readonly Panel _scrollPanel;
    private readonly FlowLayoutPanel _notificationList;
    private readonly FlowLayoutPanel _sidebarList;
    private readonly Button _acknowledgeButton;
    private readonly Label _footerHint;
    private readonly HashSet<long> _readNotifications = [];
    private readonly List<Panel> _notificationCards = [];
    private readonly List<int> _sidebarNotificationIndices = [];
    private readonly KioskKeyboardHook _kioskHook = new();
    private bool _allowClose;

    private sealed class DetailTarget
    {
        public required Notification Notification { get; init; }
        public required Label MessageLabel { get; init; }
        public required string FullMessage { get; init; }
        public bool Expanded { get; set; }
    }

    public NotificationForm(
        IReadOnlyList<Notification> notifications,
        Func<Notification, Task> onOpen,
        Func<Notification, Task> onAcknowledge)
    {
        if (notifications.Count == 0) throw new ArgumentException("At least one notification is required.", nameof(notifications));

        _notifications = notifications;
        _onOpen = onOpen;
        _onAcknowledge = onAcknowledge;

        // Every notification is now shown as a full-screen, kiosk-locked window
        // that cannot be closed, alt-tabbed away from, or minimized until the
        // employee scrolls through everything and clicks "รับทราบและปิด" -
        // the same lockdown used for Policy notifications.
        Text = "CorpNotify";
        FormBorderStyle = FormBorderStyle.None;
        StartPosition = FormStartPosition.Manual;
        Bounds = Screen.PrimaryScreen?.Bounds ?? new Rectangle(0, 0, 1280, 800);
        // Note: WindowState is intentionally left as Normal - setting it to
        // Maximized here fights with the explicit Bounds above (Windows
        // recalculates a "maximized" size against the work area, which can
        // shrink the window and push the footer/acknowledge button off
        // screen). Bounds alone already covers the full monitor.
        ShowInTaskbar = false;
        ControlBox = false;
        MaximizeBox = false;
        BackColor = PageBackground;
        TopMost = true;
        KeyPreview = true;
        Font = new Font("Segoe UI", 10);

        var header = CreateHeader(notifications);
        var instruction = new Label
        {
            Dock = DockStyle.Top,
            Height = 52,
            Text = "กรุณาอ่านประกาศทั้งหมด แล้วเลื่อนลงด้านล่างสุดเพื่อเปิดใช้งานปุ่มรับทราบ",
            BackColor = Color.White,
            ForeColor = MutedInk,
            Font = new Font("Segoe UI", 10.5f),
            Padding = new Padding(50, 14, 50, 8),
            TextAlign = ContentAlignment.MiddleLeft
        };

        _scrollPanel = new Panel
        {
            Dock = DockStyle.Fill,
            AutoScroll = true,
            BackColor = PageBackground,
            Padding = new Padding(54, 30, 54, 30)
        };
        _scrollPanel.Scroll += (_, _) => ScheduleAcknowledgeStateUpdate();
        _scrollPanel.Resize += (_, _) =>
        {
            UpdateCardWidths();
            ScheduleAcknowledgeStateUpdate();
        };
        _scrollPanel.MouseWheel += (_, _) => ScheduleAcknowledgeStateUpdate();

        var list = new FlowLayoutPanel
        {
            Name = "NotificationList",
            FlowDirection = FlowDirection.TopDown,
            WrapContents = false,
            AutoSize = true,
            AutoSizeMode = AutoSizeMode.GrowAndShrink,
            Dock = DockStyle.Top,
            Padding = new Padding(0),
            Margin = new Padding(0)
        };
        _notificationList = list;

        foreach (var notification in notifications)
        {
            var card = CreateNotificationCard(notification);
            _notificationCards.Add(card);
            list.Controls.Add(card);
        }

        _scrollPanel.Controls.Add(list);

        var footer = new Panel
        {
            Dock = DockStyle.Bottom,
            Height = 78,
            BackColor = Color.White,
            Padding = new Padding(50, 14, 50, 14)
        };
        footer.Paint += (_, e) =>
        {
            using var pen = new Pen(Border);
            e.Graphics.DrawLine(pen, 0, 0, footer.Width, 0);
        };

        _acknowledgeButton = new Button
        {
            Dock = DockStyle.Right,
            Width = 180,
            Text = "เลื่อนลงด้านล่างเพื่อปิด",
            Enabled = false,
            Font = new Font("Segoe UI", 10, FontStyle.Bold),
            FlatStyle = FlatStyle.Flat,
            BackColor = Color.FromArgb(203, 213, 225),
            ForeColor = Color.White,
            Cursor = Cursors.Hand
        };
        _acknowledgeButton.FlatAppearance.BorderSize = 0;
        _acknowledgeButton.Click += AcknowledgeClicked;
        footer.Controls.Add(_acknowledgeButton);

        var footerHint = new Label
        {
            Dock = DockStyle.Fill,
            Text = "การกดรับทราบจะยืนยันประกาศทั้งหมดในหน้าต่างนี้",
            ForeColor = MutedInk,
            Font = new Font("Segoe UI", 10),
            TextAlign = ContentAlignment.MiddleLeft
        };
        footer.Controls.Add(footerHint);
        _footerHint = footerHint;

        _sidebarList = new FlowLayoutPanel { Dock = DockStyle.Fill, FlowDirection = FlowDirection.TopDown, WrapContents = false, AutoScroll = true, Padding = new Padding(16, 12, 16, 12), BackColor = Color.White };
        var recentCutoff = DateTimeOffset.Now.AddDays(-7);
        foreach (var (notification, index) in notifications.Select((n, i) => (n, i)).Where(x => !x.n.StartAt.HasValue || x.n.StartAt.Value >= recentCutoff || x.n.WasOpened).OrderByDescending(x => x.n.StartAt ?? DateTimeOffset.MinValue))
        {
            _sidebarNotificationIndices.Add(index);
            var item = new Button { Width = 244, Height = 72, TextAlign = ContentAlignment.MiddleLeft, FlatStyle = FlatStyle.Flat, BackColor = _sidebarNotificationIndices.Count == 1 ? Color.FromArgb(239, 246, 255) : Color.White, ForeColor = Ink, Font = new Font("Segoe UI", 10, FontStyle.Bold), Text = $"{HeaderText(notification.Type)}\r\n{notification.Title}", Tag = index, Padding = new Padding(12, 8, 8, 8), AccessibleName = $"Select notification {index + 1}" };
            item.FlatAppearance.BorderColor = _sidebarNotificationIndices.Count == 1 ? Color.FromArgb(37, 99, 235) : Border;
            item.Click += SidebarItemClicked;
            _sidebarList.Controls.Add(item);
        }
        if (_sidebarList.Controls.Count == 0)
            _sidebarList.Controls.Add(new Label { AutoSize = false, Width = 244, Height = 48, Text = "ไม่มีประกาศใน 7 วันล่าสุด", ForeColor = MutedInk, Font = new Font("Segoe UI", 10), Padding = new Padding(8) });

        var contentShell = new TableLayoutPanel { Dock = DockStyle.Fill, ColumnCount = 2, RowCount = 1, BackColor = PageBackground, Padding = new Padding(28, 20, 28, 20) };
        contentShell.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute, 276));
        contentShell.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100));
        contentShell.Controls.Add(_sidebarList, 0, 0);
        contentShell.Controls.Add(_scrollPanel, 1, 0);

        Controls.Add(contentShell);
        Controls.Add(instruction);
        Controls.Add(header);
        Controls.Add(footer);
        Shown += (_, _) =>
        {
            UpdateCardWidths();
            SelectNotification(_sidebarNotificationIndices.FirstOrDefault());
            ScheduleAcknowledgeStateUpdate();
            _kioskHook.Install();
        };
        FormClosed += (_, _) => _kioskHook.Dispose();
    }

    private Panel CreateHeader(IReadOnlyList<Notification> notifications)
    {
        var header = new Panel
        {
            Dock = DockStyle.Top,
            Height = 92,
            BackColor = Ink,
            Padding = new Padding(26, 16, 26, 14)
        };

        var title = new Label
        {
            Dock = DockStyle.Top,
            Height = 34,
            Text = "CorpNotify",
            ForeColor = Color.White,
            Font = new Font("Segoe UI", 17, FontStyle.Bold)
        };
        header.Controls.Add(title);

        var subtitle = new Label
        {
            Dock = DockStyle.Fill,
            Text = notifications.Count == 1 ? "มีประกาศใหม่ 1 รายการ" : $"มีประกาศใหม่ {notifications.Count} รายการ",
            ForeColor = Color.FromArgb(203, 213, 225),
            Font = new Font("Segoe UI", 9.5f)
        };
        header.Controls.Add(subtitle);

        var countBadge = new Label
        {
            AutoSize = true,
            Text = notifications.Count.ToString(),
            BackColor = Color.FromArgb(37, 99, 235),
            ForeColor = Color.White,
            Font = new Font("Segoe UI", 11, FontStyle.Bold),
            Padding = new Padding(12, 5, 12, 5),
            Anchor = AnchorStyles.Top | AnchorStyles.Right,
            Location = new Point(Width - 78, 24),
            TextAlign = ContentAlignment.MiddleCenter
        };
        header.Controls.Add(countBadge);
        header.Resize += (_, _) => countBadge.Left = header.ClientSize.Width - countBadge.Width - 26;
        return header;
    }

    private Panel CreateNotificationCard(Notification notification)
    {
        var accent = AccentColor(notification.Type);
        var card = new Panel
        {
            Width = 700,
            AutoSize = false,
            AutoSizeMode = AutoSizeMode.GrowAndShrink,
            BackColor = Color.White,
            BorderStyle = BorderStyle.FixedSingle,
            Padding = new Padding(0),
            Margin = new Padding(0, 0, 0, 14),
            Tag = notification
        };

        var accentBar = new Panel { Dock = DockStyle.Left, Width = 6, BackColor = accent };
        card.Controls.Add(accentBar);

        var body = new Panel
        {
            Dock = DockStyle.Fill,
            AutoSize = true,
            AutoSizeMode = AutoSizeMode.GrowAndShrink,
            Padding = new Padding(20, 16, 20, 16)
        };
        card.Controls.Add(body);

        var contentWidth = Math.Max(320, card.Width - 56);
        var layout = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            AutoSize = true,
            AutoSizeMode = AutoSizeMode.GrowAndShrink,
            ColumnCount = 1,
            RowCount = 5,
            Padding = new Padding(0),
            Margin = new Padding(0)
        };
        layout.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100));

        var badge = new Label
        {
            AutoSize = true,
            Text = HeaderText(notification.Type),
            BackColor = accent,
            ForeColor = Color.White,
            Font = new Font("Segoe UI", 8.5f, FontStyle.Bold),
            Padding = new Padding(9, 4, 9, 4),
            Margin = new Padding(0, 0, 0, 10),
            MaximumSize = new Size(contentWidth, 0)
        };
        var title = new Label
        {
            AutoSize = true,
            Text = notification.Title,
            ForeColor = Ink,
            Font = new Font("Segoe UI", 24, FontStyle.Bold),
            MaximumSize = new Size(contentWidth, 0),
            Margin = new Padding(0, 0, 0, 7)
        };
        var message = new Label
        {
            AutoSize = true,
            Text = notification.Message.Length > 360 ? notification.Message[..360] + "..." : notification.Message,
            ForeColor = Color.FromArgb(51, 65, 85),
            Font = new Font("Segoe UI", 15),
            MaximumSize = new Size(contentWidth, 0),
            Margin = new Padding(0, 0, 0, 12)
        };
        var actions = new FlowLayoutPanel
        {
            AutoSize = true,
            Dock = DockStyle.Top,
            FlowDirection = FlowDirection.RightToLeft,
            WrapContents = false,
            Margin = new Padding(0), Visible = false, Height = 0};

        if (true)
        {
            var detailTarget = new DetailTarget { Notification = notification, MessageLabel = message, FullMessage = notification.Message };
            var openButton = new Button
            {
                Text = "เปิดรายละเอียด  ›",
                AutoSize = true,
                Height = 34,
                Tag = detailTarget,
                AccessibleName = "View notification details",
                Font = new Font("Segoe UI", 9, FontStyle.Bold),
                FlatStyle = FlatStyle.Flat,
                BackColor = Color.White,
                ForeColor = Color.FromArgb(37, 99, 235),
                Cursor = Cursors.Hand,
                Padding = new Padding(10, 0, 10, 0)
            };
            openButton.FlatAppearance.BorderColor = Color.FromArgb(147, 197, 253);
            openButton.FlatAppearance.BorderSize = 1;
            openButton.Click += OpenClicked;
            actions.Controls.Add(openButton);
        }

        layout.Controls.Add(badge, 0, 0);
        layout.Controls.Add(title, 0, 1);
        layout.Controls.Add(message, 0, 2);
        if (!string.IsNullOrWhiteSpace(notification.ImageUrl))
        {
            var image = new PictureBox { Height = 180, Dock = DockStyle.Top, SizeMode = PictureBoxSizeMode.Zoom, Margin = new Padding(0, 0, 0, 12) };
            image.LoadCompleted += (_, e) => { if (e.Error != null) image.Visible = false; };
            try
            {
                if (!string.IsNullOrWhiteSpace(notification.ImageBase64))
                {
                    using var stream = new MemoryStream(Convert.FromBase64String(notification.ImageBase64));
                    using var source = Image.FromStream(stream);
                    image.Image = new Bitmap(source);
                }
                else image.LoadAsync(notification.ImageUrl);
                layout.Controls.Add(image, 0, 3);
            }
            catch { image.Dispose(); }
        }
        layout.Controls.Add(actions, 0, 4);
        body.Controls.Add(layout);
        return card;
    }

    private async void OpenClicked(object? sender, EventArgs e)
    {
        if (sender is not Button button || button.Tag is not DetailTarget target) return;

        await RunButtonActionAsync(button, async () =>
        {
            target.Expanded = !target.Expanded;
            target.MessageLabel.Text = target.Expanded
                ? target.FullMessage
                : (target.FullMessage.Length > 360 ? target.FullMessage[..360] + "..." : target.FullMessage);
            button.Text = target.Expanded ? "Hide details" : "View details";
            button.Parent?.Parent?.PerformLayout();
            button.Parent?.Parent?.Parent?.PerformLayout();
            ScheduleAcknowledgeStateUpdate();
            if (target.Expanded) await _onOpen(target.Notification);
        });
    }

    private async void SidebarItemClicked(object? sender, EventArgs e)
    {
        if (sender is not Button button || button.Tag is not int index) return;
        SelectNotification(index);
        if (index >= 0 && index < _notifications.Count && _readNotifications.Add(_notifications[index].Id))
        {
            try { await _onOpen(_notifications[index]); } catch { }
        }
    }

    private void SelectNotification(int index)
    {
        if (index < 0 || index >= _notificationCards.Count) return;
        for (var i = 0; i < _notificationCards.Count; i++) _notificationCards[i].Visible = i == index;
        for (var i = 0; i < _sidebarList.Controls.Count; i++)
        {
            if (_sidebarList.Controls[i] is Button item)
            {
                var selected = item.Tag is int itemIndex && itemIndex == index;
                item.BackColor = selected ? Color.FromArgb(239, 246, 255) : Color.White;
                item.FlatAppearance.BorderColor = selected ? Color.FromArgb(37, 99, 235) : Border;
            }
        }
        _scrollPanel.ScrollControlIntoView(_notificationCards[index]);
        _footerHint.Text = $"อ่านแล้ว {_readNotifications.Count} จาก {_notifications.Count} รายการ";
        UpdateAcknowledgeState();
    }

    private async void AcknowledgeClicked(object? sender, EventArgs e)
    {
        await RunButtonActionAsync(_acknowledgeButton, async () =>
        {
            foreach (var notification in _notifications)
            {
                await _onAcknowledge(notification);
            }

            _allowClose = true;
            Close();
        });
    }

    protected override void OnFormClosing(FormClosingEventArgs e)
    {
        if (!_allowClose && e.CloseReason == CloseReason.UserClosing)
        {
            e.Cancel = true;
        }

        base.OnFormClosing(e);
    }

    protected override bool ProcessCmdKey(ref Message msg, Keys keyData)
    {
        // Defense in depth: swallow Alt+F4 at the WinForms message-loop level too,
        // in case the low-level keyboard hook could not be installed.
        if (keyData == (Keys.Alt | Keys.F4)) return true;
        return base.ProcessCmdKey(ref msg, keyData);
    }

    private void UpdateCardWidths()
    {
        if (_scrollPanel.Controls["NotificationList"] is not FlowLayoutPanel list) return;

        var width = Math.Max(460, _scrollPanel.ClientSize.Width - _scrollPanel.Padding.Horizontal - SystemInformation.VerticalScrollBarWidth - 4);
        list.Width = width;
        foreach (Control card in list.Controls)
        {
            card.AutoSize = false;
            card.Width = width;
            card.Height = Math.Max(card.PreferredSize.Height, 180);
        }
    }

    private void UpdateAcknowledgeState()
    {
        var contentHeight = _scrollPanel.DisplayRectangle.Height;
        var viewportHeight = _scrollPanel.ClientSize.Height;
        var isAtBottom = contentHeight <= viewportHeight + 2 && _readNotifications.Count >= _notifications.Count;

        if (!isAtBottom && _scrollPanel.VerticalScroll.Visible)
        {
            var scrollBar = _scrollPanel.VerticalScroll;
            var lastScrollValue = Math.Max(scrollBar.Minimum, scrollBar.Maximum - scrollBar.LargeChange + 1);
            isAtBottom = scrollBar.Value >= lastScrollValue - 1;
        }

        _acknowledgeButton.Enabled = isAtBottom;
        _acknowledgeButton.BackColor = isAtBottom ? Color.FromArgb(37, 99, 235) : Color.FromArgb(203, 213, 225);
        _acknowledgeButton.Text = isAtBottom ? "รับทราบและปิด" : "เลื่อนลงด้านล่างเพื่อปิด";
    }

    private void ScheduleAcknowledgeStateUpdate()
    {
        if (IsDisposed || !IsHandleCreated) return;

        BeginInvoke(new Action(() =>
        {
            if (!IsDisposed) UpdateAcknowledgeState();
        }));
    }

    private static async Task RunButtonActionAsync(Button button, Func<Task> action)
    {
        button.Enabled = false;
        try { await action(); }
        catch { button.Enabled = true; }
    }

    internal static bool IsSafeWebUrl(string? url) => TryGetSafeWebUri(url, out _);

    private static bool TryGetSafeWebUri(string? url, out Uri uri)
    {
        return Uri.TryCreate(url, UriKind.Absolute, out uri!) &&
               (uri.Scheme == Uri.UriSchemeHttp || uri.Scheme == Uri.UriSchemeHttps);
    }

    private static Color AccentColor(string type) => type.ToLowerInvariant() switch
    {
        "critical" => Color.FromArgb(220, 38, 38),
        "warning" => Color.FromArgb(234, 88, 12),
        _ => Color.FromArgb(37, 99, 235)
    };

    private static string HeaderText(string type) => type.ToLowerInvariant() switch
    {
        "critical" => "CRITICAL",
        "warning" => "WARNING",
        _ => "INFO"
    };
}







