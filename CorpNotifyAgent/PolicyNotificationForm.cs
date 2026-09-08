using CorpNotifyAgent.Models;

namespace CorpNotifyAgent;

/// <summary>
/// Full-screen, kiosk-locked, 3-step flow for notifications that require policy
/// acknowledgement: Notice -> Policy content -> Quiz. The window cannot be closed,
/// alt-tabbed away from, or task-switched until the quiz is answered correctly and
/// the final acknowledge call succeeds.
/// </summary>
internal sealed class PolicyNotificationForm : Form
{
    private static readonly Color PageBackground = Color.FromArgb(246, 248, 251);
    private static readonly Color Ink = Color.FromArgb(15, 23, 42);
    private static readonly Color MutedInk = Color.FromArgb(100, 116, 139);
    private static readonly Color Border = Color.FromArgb(230, 234, 240);
    private static readonly Color BlueAccent = Color.FromArgb(37, 99, 235);
    private static readonly Color RedAccent = Color.FromArgb(220, 38, 38);
    private static readonly Color GreenAccent = Color.FromArgb(22, 163, 74);
    private static readonly Color DisabledGray = Color.FromArgb(203, 213, 225);

    private readonly Notification _notification;
    private readonly Func<Notification, Task> _onOpened;
    private readonly Func<Notification, Task> _onReadCompleted;
    private readonly Func<Notification, Task> _onQuizStarted;
    private readonly Func<Notification, IReadOnlyList<(long QuestionId, string Answer)>, Task<QuizSubmitResult>> _onSubmitQuiz;
    private readonly Func<Notification, Task> _onAcknowledge;
    private readonly KioskKeyboardHook _kioskHook = new();

    private readonly Panel _contentHost;
    private readonly Panel _noticePanel;
    private readonly Panel _policyPanel;
    private readonly Panel _quizPanel;
    private readonly Panel _policyScroll;
    private readonly Button _policyAckButton;
    private readonly Button _quizSubmitButton;
    private readonly Label _quizStatusLabel;
    private readonly Dictionary<long, (Button UseButton, Button NotUseButton)> _quizRows = new();
    private readonly Dictionary<long, string> _selectedAnswers = new();
    private readonly Label[] _stepDots = new Label[3];

    private bool _allowClose;
    private bool _readCompletedReported;

    public PolicyNotificationForm(
        Notification notification,
        Func<Notification, Task> onOpened,
        Func<Notification, Task> onReadCompleted,
        Func<Notification, Task> onQuizStarted,
        Func<Notification, IReadOnlyList<(long QuestionId, string Answer)>, Task<QuizSubmitResult>> onSubmitQuiz,
        Func<Notification, Task> onAcknowledge)
    {
        _notification = notification;
        _onOpened = onOpened;
        _onReadCompleted = onReadCompleted;
        _onQuizStarted = onQuizStarted;
        _onSubmitQuiz = onSubmitQuiz;
        _onAcknowledge = onAcknowledge;

        Text = "CorpNotify";
        FormBorderStyle = FormBorderStyle.None;
        StartPosition = FormStartPosition.Manual;
        Bounds = Screen.PrimaryScreen?.Bounds ?? new Rectangle(0, 0, 1280, 800);
        // Note: WindowState left as Normal - see NotificationForm.cs for why
        // combining it with an explicit Bounds shrinks the window and hides
        // footer buttons.
        ShowInTaskbar = false;
        ControlBox = false;
        TopMost = true;
        KeyPreview = true;
        BackColor = PageBackground;
        Font = new Font("Segoe UI", 10);

        Controls.Add(_contentHost = new Panel { Dock = DockStyle.Fill, BackColor = PageBackground });
        Controls.Add(CreateHeader());

        _noticePanel = BuildNoticePanel();
        _policyPanel = BuildPolicyPanel(out _policyScroll, out _policyAckButton);
        _quizPanel = BuildQuizPanel(out _quizSubmitButton, out _quizStatusLabel);

        _contentHost.Controls.Add(_quizPanel);
        _contentHost.Controls.Add(_policyPanel);
        _contentHost.Controls.Add(_noticePanel);

        ShowStep(_noticePanel);

        Shown += (_, _) => _kioskHook.Install();
        FormClosed += (_, _) => _kioskHook.Dispose();
    }

    private Panel CreateHeader()
    {
        var header = new Panel { Dock = DockStyle.Top, Height = 96, BackColor = Ink, Padding = new Padding(30, 16, 30, 16) };

        header.Controls.Add(new Label
        {
            Dock = DockStyle.Left,
            AutoSize = false,
            Width = 260,
            Text = "CorpNotify",
            ForeColor = Color.White,
            Font = new Font("Segoe UI", 17, FontStyle.Bold),
            TextAlign = ContentAlignment.MiddleLeft
        });

        var steps = new FlowLayoutPanel
        {
            Dock = DockStyle.Right,
            Width = 520,
            FlowDirection = FlowDirection.LeftToRight,
            WrapContents = false
        };

        string[] labels = ["1  ประกาศ", "2  นโยบาย", "3  แบบทดสอบ"];
        for (var i = 0; i < labels.Length; i++)
        {
            var dot = new Label
            {
                AutoSize = false,
                Width = 150,
                Height = 40,
                Text = labels[i],
                TextAlign = ContentAlignment.MiddleCenter,
                Font = new Font("Segoe UI", 9.5f, FontStyle.Bold),
                Margin = new Padding(4, 12, 4, 0)
            };
            _stepDots[i] = dot;
            steps.Controls.Add(dot);
        }

        header.Controls.Add(steps);
        return header;
    }

    private void RefreshStepIndicator(int activeIndex)
    {
        for (var i = 0; i < _stepDots.Length; i++)
        {
            var active = i == activeIndex;
            _stepDots[i].BackColor = active ? BlueAccent : Color.FromArgb(30, 41, 59);
            _stepDots[i].ForeColor = active ? Color.White : Color.FromArgb(148, 163, 184);
        }
    }

    private void ShowStep(Panel panel)
    {
        foreach (Control control in _contentHost.Controls)
        {
            control.Visible = ReferenceEquals(control, panel);
        }

        RefreshStepIndicator(panel == _noticePanel ? 0 : panel == _policyPanel ? 1 : 2);
    }

    // ---------- Step 1: Notice ----------

    private Panel BuildNoticePanel()
    {
        var panel = new Panel { Dock = DockStyle.Fill, BackColor = PageBackground, Padding = new Padding(60, 50, 60, 50) };

        var accent = AccentColor(_notification.Type);
        var badge = new Label
        {
            AutoSize = true,
            Text = HeaderText(_notification.Type),
            BackColor = accent,
            ForeColor = Color.White,
            Font = new Font("Segoe UI", 9, FontStyle.Bold),
            Padding = new Padding(10, 5, 10, 5),
            Location = new Point(0, 0)
        };

        var title = new Label
        {
            AutoSize = true,
            Text = _notification.Title,
            ForeColor = Ink,
            Font = new Font("Segoe UI", 24, FontStyle.Bold),
            Location = new Point(0, 40),
            MaximumSize = new Size(900, 0)
        };

        var message = new Label
        {
            AutoSize = true,
            Text = _notification.Message,
            ForeColor = Color.FromArgb(51, 65, 85),
            Font = new Font("Segoe UI", 15),
            Location = new Point(0, 110),
            MaximumSize = new Size(900, 0)
        };

        var hint = new Label
        {
            AutoSize = true,
            Text = "ประกาศนี้ต้องอ่านนโยบายและทำแบบทดสอบให้ครบก่อนจึงจะปิดหน้าต่างนี้ได้",
            ForeColor = MutedInk,
            Font = new Font("Segoe UI", 10.5f, FontStyle.Italic),
            Location = new Point(0, 200)
        };

        var policyButton = new Button
        {
            Text = "Policy  ›",
            Width = 220,
            Height = 46,
            Location = new Point(0, 250),
            Font = new Font("Segoe UI", 11, FontStyle.Bold),
            FlatStyle = FlatStyle.Flat,
            BackColor = BlueAccent,
            ForeColor = Color.White,
            Cursor = Cursors.Hand
        };
        policyButton.FlatAppearance.BorderSize = 0;
        policyButton.Click += async (_, _) =>
        {
            await SafeInvokeAsync(() => _onOpened(_notification));
            ShowStep(_policyPanel);
            UpdatePolicyAckState();
        };

        panel.Controls.Add(badge);
        panel.Controls.Add(title);
        panel.Controls.Add(message);
        panel.Controls.Add(hint);
        panel.Controls.Add(policyButton);
        return panel;
    }

    // ---------- Step 2: Policy content ----------

    private Panel BuildPolicyPanel(out Panel scrollPanel, out Button ackButton)
    {
        var panel = new Panel { Dock = DockStyle.Fill, BackColor = PageBackground, Visible = false };

        var instruction = new Label
        {
            Dock = DockStyle.Top,
            Height = 46,
            Text = "กรุณาอ่านนโยบายทั้งหมด แล้วเลื่อนลงด้านล่างสุดเพื่อเปิดใช้งานปุ่มรับทราบ",
            BackColor = Color.White,
            ForeColor = MutedInk,
            Font = new Font("Segoe UI", 10.5f),
            Padding = new Padding(40, 13, 40, 8),
            TextAlign = ContentAlignment.MiddleLeft
        };

        scrollPanel = new Panel { Dock = DockStyle.Fill, AutoScroll = true, BackColor = Color.White, Padding = new Padding(40, 24, 40, 24) };
        var contentLabel = new Label
        {
            AutoSize = true,
            Text = _notification.PolicyBody ?? string.Empty,
            ForeColor = Color.FromArgb(30, 41, 59),
            Font = new Font("Segoe UI", 15),
            MaximumSize = new Size(880, 0)
        };
        scrollPanel.Controls.Add(contentLabel);
        scrollPanel.Scroll += (_, _) => UpdatePolicyAckState();
        scrollPanel.MouseWheel += (_, _) => UpdatePolicyAckState();
        scrollPanel.Resize += (_, _) => UpdatePolicyAckState();

        var footer = new Panel { Dock = DockStyle.Bottom, Height = 78, BackColor = Color.White, Padding = new Padding(40, 14, 40, 14) };
        footer.Paint += (_, e) => { using var pen = new Pen(Border); e.Graphics.DrawLine(pen, 0, 0, footer.Width, 0); };

        ackButton = new Button
        {
            Dock = DockStyle.Right,
            Width = 200,
            Text = "เลื่อนลงเพื่ออ่านต่อ",
            Enabled = false,
            Font = new Font("Segoe UI", 10, FontStyle.Bold),
            FlatStyle = FlatStyle.Flat,
            BackColor = DisabledGray,
            ForeColor = Color.White,
            Cursor = Cursors.Hand
        };
        ackButton.FlatAppearance.BorderSize = 0;
        ackButton.Click += async (_, _) =>
        {
            await RunButtonActionAsync(_policyAckButton, async () =>
            {
                if (!_readCompletedReported)
                {
                    await _onReadCompleted(_notification);
                    _readCompletedReported = true;
                }

                if (_notification.Questions.Count == 0)
                {
                    await _onAcknowledge(_notification);
                    _allowClose = true;
                    Close();
                    return;
                }

                await _onQuizStarted(_notification);
                ShowStep(_quizPanel);
            });
        };
        footer.Controls.Add(ackButton);

        panel.Controls.Add(scrollPanel);
        panel.Controls.Add(instruction);
        panel.Controls.Add(footer);
        return panel;
    }

    private void UpdatePolicyAckState()
    {
        if (IsDisposed) return;

        var contentHeight = _policyScroll.DisplayRectangle.Height;
        var viewportHeight = _policyScroll.ClientSize.Height;
        var isAtBottom = contentHeight <= viewportHeight + 2;

        if (!isAtBottom && _policyScroll.VerticalScroll.Visible)
        {
            var scrollBar = _policyScroll.VerticalScroll;
            var lastScrollValue = Math.Max(scrollBar.Minimum, scrollBar.Maximum - scrollBar.LargeChange + 1);
            isAtBottom = scrollBar.Value >= lastScrollValue - 1;
        }

        _policyAckButton.Enabled = isAtBottom;
        _policyAckButton.BackColor = isAtBottom ? BlueAccent : DisabledGray;
        _policyAckButton.Text = isAtBottom ? "รับทราบ  ›" : "เลื่อนลงเพื่ออ่านต่อ";
    }

    // ---------- Step 3: Quiz ----------

    private Panel BuildQuizPanel(out Button submitButton, out Label statusLabel)
    {
        var panel = new Panel { Dock = DockStyle.Fill, BackColor = PageBackground, Visible = false };

        var instruction = new Label
        {
            Dock = DockStyle.Top,
            Height = 60,
            Text = "แบบทดสอบความเข้าใจ: กรุณาตอบทุกข้อ (ใช่ / ไม่ใช่) ให้ถูกต้องเพื่อรับทราบและปิดหน้าต่างนี้",
            BackColor = Color.White,
            ForeColor = MutedInk,
            Font = new Font("Segoe UI", 10.5f),
            Padding = new Padding(40, 14, 40, 8),
            TextAlign = ContentAlignment.MiddleLeft
        };

        var scroll = new Panel { Dock = DockStyle.Fill, AutoScroll = true, BackColor = PageBackground, Padding = new Padding(40, 20, 40, 20) };

        var list = new FlowLayoutPanel
        {
            FlowDirection = FlowDirection.TopDown,
            WrapContents = false,
            AutoSize = true,
            AutoSizeMode = AutoSizeMode.GrowAndShrink,
            Width = 880
        };

        foreach (var question in _notification.Questions)
        {
            list.Controls.Add(BuildQuizRow(question));
        }

        scroll.Controls.Add(list);

        var footer = new Panel { Dock = DockStyle.Bottom, Height = 90, BackColor = Color.White, Padding = new Padding(40, 12, 40, 14) };
        footer.Paint += (_, e) => { using var pen = new Pen(Border); e.Graphics.DrawLine(pen, 0, 0, footer.Width, 0); };

        submitButton = new Button
        {
            Dock = DockStyle.Right,
            Width = 220,
            Height = 46,
            Text = "ส่งคำตอบ",
            Font = new Font("Segoe UI", 10, FontStyle.Bold),
            FlatStyle = FlatStyle.Flat,
            BackColor = BlueAccent,
            ForeColor = Color.White,
            Cursor = Cursors.Hand
        };
        submitButton.FlatAppearance.BorderSize = 0;
        submitButton.Click += QuizSubmitClicked;
        footer.Controls.Add(submitButton);

        statusLabel = new Label
        {
            Dock = DockStyle.Fill,
            Text = string.Empty,
            ForeColor = RedAccent,
            Font = new Font("Segoe UI", 9.5f, FontStyle.Bold),
            TextAlign = ContentAlignment.MiddleLeft
        };
        footer.Controls.Add(statusLabel);

        panel.Controls.Add(scroll);
        panel.Controls.Add(instruction);
        panel.Controls.Add(footer);
        return panel;
    }

    private Panel BuildQuizRow(QuizQuestion question)
    {
        var card = new Panel
        {
            Width = 880,
            AutoSize = true,
            AutoSizeMode = AutoSizeMode.GrowAndShrink,
            BackColor = Color.White,
            Padding = new Padding(24, 20, 24, 20),
            Margin = new Padding(0, 0, 0, 14)
        };
        // Subtle accent bar on the left edge, matching the notice-card style.
        card.Paint += (_, e) =>
        {
            using var brush = new SolidBrush(Color.FromArgb(124, 58, 237));
            e.Graphics.FillRectangle(brush, 0, 0, 4, card.Height);
        };

        var questionLabel = new Label
        {
            AutoSize = true,
            Text = question.Question,
            ForeColor = Ink,
            Font = new Font("Segoe UI", 12, FontStyle.Bold),
            MaximumSize = new Size(820, 0),
            Location = new Point(4, 0)
        };

        var optionsGroup = new Panel { AutoSize = true, Location = new Point(4, 42) };

        var useButton = CreateToggleButton("ใช้", new Point(0, 0));
        var notUseButton = CreateToggleButton("ไม่ใช้", new Point(150, 0));
        optionsGroup.Controls.Add(useButton);
        optionsGroup.Controls.Add(notUseButton);

        useButton.Click += (_, _) => SelectAnswer(question.Id, "use", useButton, notUseButton);
        notUseButton.Click += (_, _) => SelectAnswer(question.Id, "not_use", useButton, notUseButton);

        card.Controls.Add(questionLabel);
        card.Controls.Add(optionsGroup);

        _quizRows[question.Id] = (useButton, notUseButton);
        return card;
    }

    private static Button CreateToggleButton(string text, Point location) => new()
    {
        Text = text,
        Location = location,
        Size = new Size(134, 44),
        Font = new Font("Segoe UI", 10.5f, FontStyle.Bold),
        FlatStyle = FlatStyle.Flat,
        BackColor = Color.White,
        ForeColor = Color.FromArgb(51, 65, 85),
        Cursor = Cursors.Hand,
        FlatAppearance = { BorderSize = 2, BorderColor = Color.FromArgb(203, 213, 225) }
    };

    private void SelectAnswer(long questionId, string answer, Button useButton, Button notUseButton)
    {
        _selectedAnswers[questionId] = answer;

        var selected = answer == "use" ? useButton : notUseButton;
        var unselected = answer == "use" ? notUseButton : useButton;

        selected.BackColor = Color.FromArgb(124, 58, 237);
        selected.ForeColor = Color.White;
        selected.FlatAppearance.BorderColor = Color.FromArgb(124, 58, 237);

        unselected.BackColor = Color.White;
        unselected.ForeColor = Color.FromArgb(51, 65, 85);
        unselected.FlatAppearance.BorderColor = Color.FromArgb(203, 213, 225);

        _quizStatusLabel.Text = string.Empty;
    }

    private async void QuizSubmitClicked(object? sender, EventArgs e)
    {
        if (_selectedAnswers.Count < _quizRows.Count)
        {
            _quizStatusLabel.ForeColor = RedAccent;
            _quizStatusLabel.Text = "กรุณาตอบให้ครบทุกข้อก่อนส่งคำตอบ";
            return;
        }

        var answers = _quizRows.Keys
            .Select(questionId => (QuestionId: questionId, Answer: _selectedAnswers[questionId]))
            .ToList();

        await RunButtonActionAsync(_quizSubmitButton, async () =>
        {
            var result = await _onSubmitQuiz(_notification, answers);
            ApplyQuizResult(result);

            if (result.Passed)
            {
                await _onAcknowledge(_notification);
                _allowClose = true;
                Close();
            }
        });
    }

    private void ApplyQuizResult(QuizSubmitResult result)
    {
        // Intentionally do NOT reveal which specific question was wrong -
        // that would let people just flip one answer at a time until they
        // get a green check without actually re-reading the policy. Showing
        // one general message forces them to review every answer themselves.
        _quizStatusLabel.Text = result.Passed
            ? "ตอบถูกต้องครบทุกข้อ กำลังบันทึกการรับทราบ..."
            : "คุณยังมีคำถามที่ตอบผิดอยู่ กรุณาตรวจสอบคำตอบของคุณอีกครั้ง";
        _quizStatusLabel.ForeColor = result.Passed ? GreenAccent : RedAccent;
    }

    protected override void OnFormClosing(FormClosingEventArgs e)
    {
        if (!_allowClose)
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

    private static async Task RunButtonActionAsync(Button button, Func<Task> action)
    {
        button.Enabled = false;
        try { await action(); }
        catch { /* swallow so the kiosk screen never crashes the agent */ }
        finally { if (!button.IsDisposed) button.Enabled = true; }
    }

    private static async Task SafeInvokeAsync(Func<Task> action)
    {
        try { await action(); } catch { /* opened/tracking calls must never block navigation */ }
    }

    private static Color AccentColor(string type) => type.ToLowerInvariant() switch
    {
        "critical" => Color.FromArgb(220, 38, 38),
        "warning" => Color.FromArgb(234, 88, 12),
        "policy" => Color.FromArgb(124, 58, 237),
        _ => Color.FromArgb(37, 99, 235)
    };

    private static string HeaderText(string type) => type.ToLowerInvariant() switch
    {
        "critical" => "CRITICAL",
        "warning" => "WARNING",
        "policy" => "POLICY",
        _ => "INFO"
    };
}
