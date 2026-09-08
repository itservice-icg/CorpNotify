# CorpNotify Windows Agent

## Configuration

Edit `appsettings.json`. Production API URLs must use HTTPS; loopback HTTP is accepted only for local development.

```json
{
  "ApiUrl": "https://notify.company.local/api",
  "PollingIntervalSeconds": 15,
  "HeartbeatIntervalSeconds": 60,
  "Department": "IT"
}
```

## Build and test

```powershell
dotnet build ..\CorpNotify.sln -c Release
dotnet test ..\CorpNotifyAgent.Tests\CorpNotifyAgent.Tests.csproj -c Release
```

## Publish

```powershell
dotnet publish CorpNotifyAgent.csproj -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true -o ..\publish\win-x64
```

Deploy both `CorpNotifyAgent.exe` and `appsettings.json`. Device identity and logs are stored under `%ProgramData%\CorpNotify\`.
