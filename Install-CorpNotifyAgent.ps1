[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^https://|^http://(localhost|127\.0\.0\.1)(:\d+)?/')]
    [string] $ApiUrl,

    [Parameter(Mandatory = $true)]
    [ValidateNotNullOrEmpty()]
    [string] $EnrollmentKey,

    [string] $Department = '',
    [switch] $Uninstall
)

$ErrorActionPreference = 'Stop'
$taskName = 'CorpNotifyAgent'
$installDir = Join-Path $env:ProgramFiles 'CorpNotifyAgent'
$dataDir = Join-Path $env:ProgramData 'CorpNotify'
$sourceDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$exeSource = Join-Path $sourceDir 'CorpNotifyAgent.exe'

function Assert-Administrator {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = [Security.Principal.WindowsPrincipal]::new($identity)
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw 'Run this script from an elevated PowerShell window.'
    }
}

Assert-Administrator

if ($Uninstall) {
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $installDir -Recurse -Force -ErrorAction SilentlyContinue
    Write-Host 'CorpNotifyAgent startup task and installed files removed.'
    exit 0
}

if (-not (Test-Path -LiteralPath $exeSource)) {
    throw "CorpNotifyAgent.exe was not found beside this installer script."
}

New-Item -ItemType Directory -Path $installDir -Force | Out-Null
New-Item -ItemType Directory -Path $dataDir -Force | Out-Null
Copy-Item -LiteralPath $exeSource -Destination (Join-Path $installDir 'CorpNotifyAgent.exe') -Force

$settings = [ordered]@{
    ApiUrl = $ApiUrl.TrimEnd('/')
    PollingIntervalSeconds = 15
    HeartbeatIntervalSeconds = 60
    Department = $Department
    EnrollmentKey = $EnrollmentKey
}
$settings | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $installDir 'appsettings.json') -Encoding utf8

$action = New-ScheduledTaskAction -Execute (Join-Path $installDir 'CorpNotifyAgent.exe') -WorkingDirectory $installDir
$trigger = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME
$principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Limited
$settingsTask = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $settingsTask -Force | Out-Null

Start-ScheduledTask -TaskName $taskName
Write-Host "CorpNotifyAgent installed to $installDir and started for user $env:USERNAME."
