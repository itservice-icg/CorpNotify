using System.Net;
using System.Net.NetworkInformation;
using System.Net.Sockets;
using System.Reflection;
using System.Text.Json;
using CorpNotifyAgent.Models;

namespace CorpNotifyAgent;

internal sealed class DeviceManager
{
    private readonly string _deviceFile;
    private readonly string _authFile;

    public DeviceManager(string dataDirectory)
    {
        _deviceFile = Path.Combine(dataDirectory, "device.json");
        _authFile = Path.Combine(dataDirectory, "agent-auth.json");
    }

    public async Task<DeviceIdentity> LoadOrCreateAsync(CancellationToken cancellationToken)
    {
        if (File.Exists(_deviceFile))
        {
            await using var existing = File.OpenRead(_deviceFile);
            var identity = await JsonSerializer.DeserializeAsync<DeviceIdentity>(existing, cancellationToken: cancellationToken);
            if (identity is not null && Guid.TryParse(identity.DeviceUuid, out _))
            {
                if (File.Exists(_authFile))
                {
                    await using var auth = File.OpenRead(_authFile);
                    var storedAuth = await JsonSerializer.DeserializeAsync<DeviceIdentity>(auth, cancellationToken: cancellationToken);
                    identity.ApiToken = storedAuth?.ApiToken;
                }
                return identity;
            }
        }

        var created = new DeviceIdentity { DeviceUuid = Guid.NewGuid().ToString() };
        await using var output = File.Create(_deviceFile);
        await JsonSerializer.SerializeAsync(output, created, cancellationToken: cancellationToken);
        return created;
    }

    public async Task SaveAsync(DeviceIdentity identity, CancellationToken cancellationToken)
    {
        var authOnly = new DeviceIdentity { DeviceUuid = identity.DeviceUuid, ApiToken = identity.ApiToken };
        await using var output = File.Create(_authFile);
        await JsonSerializer.SerializeAsync(output, authOnly, cancellationToken: cancellationToken);
    }

    public static DeviceRegistration BuildRegistration(DeviceIdentity identity, AgentConfiguration configuration) => new()
    {
        DeviceUuid = identity.DeviceUuid,
        Hostname = Environment.MachineName,
        Username = Environment.UserName,
        IpAddress = GetIpAddress(),
        Department = string.IsNullOrWhiteSpace(configuration.Department) ? null : configuration.Department.Trim(),
        AgentVersion = Assembly.GetExecutingAssembly().GetName().Version?.ToString(3) ?? "1.0.0"
    };

    private static string? GetIpAddress()
    {
        return NetworkInterface.GetAllNetworkInterfaces()
            .Where(network => network.OperationalStatus == OperationalStatus.Up && network.NetworkInterfaceType != NetworkInterfaceType.Loopback)
            .SelectMany(network => network.GetIPProperties().UnicastAddresses)
            .Select(address => address.Address)
            .FirstOrDefault(address => address.AddressFamily == AddressFamily.InterNetwork && !IPAddress.IsLoopback(address))
            ?.ToString();
    }
}
