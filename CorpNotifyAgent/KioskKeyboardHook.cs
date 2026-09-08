using System.Runtime.InteropServices;

namespace CorpNotifyAgent;

/// <summary>
/// Installs a low-level keyboard hook that swallows Alt+Tab, Alt+Esc, Ctrl+Esc,
/// the Windows key, and Alt+F4 while active, so the kiosk-style Policy/Quiz
/// screen cannot be switched away from or closed via keyboard shortcuts.
/// Note: Ctrl+Alt+Delete is protected by Windows itself and cannot be intercepted
/// by any application-level hook - this is an OS security boundary, not a bug.
/// </summary>
internal sealed class KioskKeyboardHook : IDisposable
{
    private const int WH_KEYBOARD_LL = 13;
    private const int WM_KEYDOWN = 0x0100;
    private const int WM_SYSKEYDOWN = 0x0104;

    private const int VK_TAB = 0x09;
    private const int VK_ESCAPE = 0x1B;
    private const int VK_F4 = 0x73;
    private const int VK_LWIN = 0x5B;
    private const int VK_RWIN = 0x5C;

    private readonly LowLevelKeyboardProc _proc;
    private IntPtr _hookId = IntPtr.Zero;

    public KioskKeyboardHook()
    {
        _proc = HookCallback;
    }

    public void Install()
    {
        if (_hookId != IntPtr.Zero) return;

        using var currentProcess = System.Diagnostics.Process.GetCurrentProcess();
        using var currentModule = currentProcess.MainModule!;
        _hookId = SetWindowsHookEx(WH_KEYBOARD_LL, _proc, GetModuleHandle(currentModule.ModuleName), 0);
    }

    public void Uninstall()
    {
        if (_hookId == IntPtr.Zero) return;
        UnhookWindowsHookEx(_hookId);
        _hookId = IntPtr.Zero;
    }

    private IntPtr HookCallback(int nCode, IntPtr wParam, IntPtr lParam)
    {
        if (nCode >= 0 && (wParam == WM_KEYDOWN || wParam == WM_SYSKEYDOWN))
        {
            var vkCode = Marshal.ReadInt32(lParam);
            var altPressed = (GetAsyncKeyState(0x12) & 0x8000) != 0;
            var ctrlPressed = (GetAsyncKeyState(0x11) & 0x8000) != 0;

            var blockCombo =
                (altPressed && vkCode == VK_TAB) ||
                (altPressed && vkCode == VK_ESCAPE) ||
                (altPressed && vkCode == VK_F4) ||
                (ctrlPressed && vkCode == VK_ESCAPE) ||
                vkCode is VK_LWIN or VK_RWIN;

            if (blockCombo) return (IntPtr)1;
        }

        return CallNextHookEx(_hookId, nCode, wParam, lParam);
    }

    public void Dispose() => Uninstall();

    private delegate IntPtr LowLevelKeyboardProc(int nCode, IntPtr wParam, IntPtr lParam);

    [DllImport("user32.dll", CharSet = CharSet.Auto, SetLastError = true)]
    private static extern IntPtr SetWindowsHookEx(int idHook, LowLevelKeyboardProc lpfn, IntPtr hMod, uint dwThreadId);

    [DllImport("user32.dll", CharSet = CharSet.Auto, SetLastError = true)]
    [return: MarshalAs(UnmanagedType.Bool)]
    private static extern bool UnhookWindowsHookEx(IntPtr hhk);

    [DllImport("user32.dll", CharSet = CharSet.Auto, SetLastError = true)]
    private static extern IntPtr CallNextHookEx(IntPtr hhk, int nCode, IntPtr wParam, IntPtr lParam);

    [DllImport("kernel32.dll", CharSet = CharSet.Auto, SetLastError = true)]
    private static extern IntPtr GetModuleHandle(string? lpModuleName);

    [DllImport("user32.dll")]
    private static extern short GetAsyncKeyState(int vKey);
}
