using CorpNotifyAgent.Models;

namespace CorpNotifyAgent;

internal static class Program
{
    private static Mutex? _singleInstance;

    [STAThread]
    private static async Task Main()
    {
        _singleInstance = new Mutex(true, "Local\\CorpNotifyAgent.SingleInstance", out var isFirstInstance);
        if (!isFirstInstance) return;

        ApplicationConfiguration.Initialize();

        var dataDirectory = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), "CorpNotify");
        Directory.CreateDirectory(dataDirectory);
        var logger = new AgentLogger(dataDirectory);

        try
        {
            var configuration = await ConfigManager.LoadAsync(CancellationToken.None);
            var deviceManager = new DeviceManager(dataDirectory);
            var identity = await deviceManager.LoadOrCreateAsync(CancellationToken.None);
            DeviceRegistration registration = DeviceManager.BuildRegistration(identity, configuration);
            var apiClient = new ApiClient(configuration.ApiUrl, identity.ApiToken);
            Application.Run(new AgentApplicationContext(configuration, logger, apiClient, registration, deviceManager, identity));
        }
        catch (Exception exception)
        {
            await logger.ErrorAsync($"Agent startup failed: {exception.GetType().Name} - {exception.Message}");
            MessageBox.Show("CorpNotify Agent ไม่สามารถเริ่มทำงานได้ กรุณาติดต่อผู้ดูแลระบบ", "CorpNotify", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }
}
