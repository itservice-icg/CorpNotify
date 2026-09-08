namespace CorpNotifyAgent;

internal class LockedAgentForm : Form
{
    private const int WmSysCommand = 0x0112;
    private const int ScMinimize = 0xF020;
    private const int ScClose = 0xF060;
    private const int CsNoclose = 0x200;

    protected bool AllowClose;

    protected LockedAgentForm()
    {
        Text = "CorpNotify";
        StartPosition = FormStartPosition.CenterScreen;
        FormBorderStyle = FormBorderStyle.None;
        WindowState = FormWindowState.Maximized;
        ControlBox = false;
        MinimizeBox = false;
        MaximizeBox = false;
        ShowInTaskbar = true;
        KeyPreview = true;
        KeyDown += (_, e) =>
        {
            if (!AllowClose && e.Alt && e.KeyCode == Keys.F4)
            {
                e.SuppressKeyPress = true;
                e.Handled = true;
            }
        };
    }

    protected override CreateParams CreateParams
    {
        get
        {
            var createParams = base.CreateParams;
            createParams.ClassStyle |= CsNoclose;
            return createParams;
        }
    }

    protected override void WndProc(ref Message message)
    {
        if (message.Msg == WmSysCommand)
        {
            var command = message.WParam.ToInt32() & 0xFFF0;
            if (!AllowClose && (command == ScMinimize || command == ScClose))
            {
                return;
            }
        }

        base.WndProc(ref message);
    }

    protected override void OnFormClosing(FormClosingEventArgs e)
    {
        if (!AllowClose && e.CloseReason == CloseReason.UserClosing)
        {
            e.Cancel = true;
        }

        base.OnFormClosing(e);
    }
}
