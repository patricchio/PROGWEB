[CmdletBinding()]
param(
    [ValidateRange(1024, 65535)]
    [int]$Port = 8080,

    [ValidatePattern('^[A-Za-z0-9_-]{1,32}$')]
    [string]$AccessUsername = 'demo',

    [string]$AccessPassword = ''
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$runtimeDirectory = Join-Path $projectRoot '.runtime'
$statePath = Join-Path $runtimeDirectory 'public-demo.json'
$routerPath = Join-Path $projectRoot 'public-router.php'
$phpPath = 'C:\xampp\php\php.exe'
$cloudflaredCandidates = @(
    (Join-Path $projectRoot '.tools\cloudflared.exe'),
    'C:\Program Files\cloudflared\cloudflared.exe',
    'C:\Program Files (x86)\cloudflared\cloudflared.exe'
)

$cloudflaredCommand = Get-Command cloudflared -ErrorAction SilentlyContinue
if ($null -ne $cloudflaredCommand) {
    $cloudflaredCandidates += $cloudflaredCommand.Source
}
$cloudflaredPath = $cloudflaredCandidates | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1

if (-not (Test-Path -LiteralPath $phpPath)) {
    throw "PHP di XAMPP non trovato in $phpPath"
}
if ([string]::IsNullOrWhiteSpace($cloudflaredPath)) {
    throw 'cloudflared non trovato. Scaricalo in .tools\cloudflared.exe e riprova.'
}
if (-not (Test-Path -LiteralPath $routerPath)) {
    throw "Router pubblico non trovato in $routerPath"
}

if (Test-Path -LiteralPath $statePath) {
        $oldState = Get-Content -Raw -LiteralPath $statePath | ConvertFrom-Json
    if ($oldState.active -eq $true) {
        $oldPhp = Get-Process -Id ([int]$oldState.phpPid) -ErrorAction SilentlyContinue
        $oldTunnel = Get-Process -Id ([int]$oldState.cloudflaredPid) -ErrorAction SilentlyContinue
        $oldPhpIsRunning = $null -ne $oldPhp -and $oldPhp.ProcessName -eq 'php'
        $oldTunnelIsRunning = $null -ne $oldTunnel -and $oldTunnel.ProcessName -eq 'cloudflared'
        if ($oldPhpIsRunning -and $oldTunnelIsRunning) {
            Write-Host 'La demo pubblica e gia attiva.' -ForegroundColor Green
            Write-Host "URL:      $($oldState.publicUrl)"
            Write-Host "Utente:   $($oldState.username)"
            Write-Host "Password: $($oldState.password)"
            Write-Host ''
            Write-Host 'Per riavviarla: esegui prima .\scripts\Stop-PublicDemo.ps1'
            exit 0
        }
        $oldState.active = $false
        $oldState | ConvertTo-Json | Set-Content -LiteralPath $statePath -Encoding UTF8
    }
}

$listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, $Port)
try {
    $listener.Start()
} catch {
    throw "La porta $Port e gia occupata. Scegline un'altra con -Port."
} finally {
    $listener.Stop()
}

& $phpPath (Join-Path $PSScriptRoot 'preflight.php')
if ($LASTEXITCODE -ne 0) {
    throw 'I controlli preliminari non sono riusciti.'
}

if ([string]::IsNullOrWhiteSpace($AccessPassword)) {
    $AccessPassword = [Guid]::NewGuid().ToString('N').Substring(0, 14)
}
if ($AccessPassword.Length -lt 10) {
    throw 'La password della demo deve contenere almeno 10 caratteri.'
}

New-Item -ItemType Directory -Path $runtimeDirectory -Force | Out-Null
$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$phpOutput = Join-Path $runtimeDirectory "php-$timestamp.out.log"
$phpError = Join-Path $runtimeDirectory "php-$timestamp.err.log"
$tunnelOutput = Join-Path $runtimeDirectory "cloudflared-$timestamp.out.log"
$tunnelError = Join-Path $runtimeDirectory "cloudflared-$timestamp.err.log"

$phpProcess = $null
$tunnelProcess = $null
$previousUsername = $env:DEMO_ACCESS_USERNAME
$previousPassword = $env:DEMO_ACCESS_PASSWORD

try {
    $env:DEMO_ACCESS_USERNAME = $AccessUsername
    $env:DEMO_ACCESS_PASSWORD = $AccessPassword
    $phpArguments = @(
        '-S',
        "127.0.0.1:$Port",
        '-t',
        "`"$projectRoot`"",
        "`"$routerPath`""
    )
    $phpProcess = Start-Process -FilePath $phpPath -ArgumentList $phpArguments `
        -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru `
        -RedirectStandardOutput $phpOutput -RedirectStandardError $phpError
} finally {
    $env:DEMO_ACCESS_USERNAME = $previousUsername
    $env:DEMO_ACCESS_PASSWORD = $previousPassword
}

try {
    Start-Sleep -Milliseconds 700
    if ($phpProcess.HasExited) {
        throw "Il server PHP non si e avviato. Controlla $phpError"
    }

    $credentialText = "${AccessUsername}:${AccessPassword}"
    $credentialBytes = [Text.Encoding]::UTF8.GetBytes($credentialText)
    $authorization = 'Basic ' + [Convert]::ToBase64String($credentialBytes)
    $localResponse = Invoke-WebRequest -Uri "http://127.0.0.1:$Port/" `
        -Headers @{ Authorization = $authorization } -UseBasicParsing -TimeoutSec 8
    if ($localResponse.StatusCode -ne 200) {
        throw "Il server locale ha risposto con HTTP $($localResponse.StatusCode)."
    }

    $tunnelArguments = @('tunnel', '--no-autoupdate', '--url', "http://127.0.0.1:$Port")
    $tunnelProcess = Start-Process -FilePath $cloudflaredPath -ArgumentList $tunnelArguments `
        -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru `
        -RedirectStandardOutput $tunnelOutput -RedirectStandardError $tunnelError

    $publicUrl = $null
    $deadline = (Get-Date).AddSeconds(30)
    while ((Get-Date) -lt $deadline -and [string]::IsNullOrWhiteSpace($publicUrl)) {
        Start-Sleep -Milliseconds 500
        if ($tunnelProcess.HasExited) {
            throw "Cloudflare Tunnel si e arrestato. Controlla $tunnelError"
        }
        $logText = ''
        if (Test-Path -LiteralPath $tunnelOutput) {
            $logText += Get-Content -Raw -LiteralPath $tunnelOutput
        }
        if (Test-Path -LiteralPath $tunnelError) {
            $logText += Get-Content -Raw -LiteralPath $tunnelError
        }
        $match = [regex]::Match($logText, 'https://[a-z0-9-]+\.trycloudflare\.com')
        if ($match.Success) {
            $publicUrl = $match.Value
        }
    }
    if ([string]::IsNullOrWhiteSpace($publicUrl)) {
        throw 'Cloudflare non ha restituito un URL pubblico entro 30 secondi.'
    }

    $state = [ordered]@{
        active = $true
        startedAt = (Get-Date).ToString('o')
        publicUrl = $publicUrl
        localUrl = "http://127.0.0.1:$Port/"
        port = $Port
        username = $AccessUsername
        password = $AccessPassword
        phpPid = $phpProcess.Id
        cloudflaredPid = $tunnelProcess.Id
        phpErrorLog = $phpError
        tunnelErrorLog = $tunnelError
    }
    $state | ConvertTo-Json | Set-Content -LiteralPath $statePath -Encoding UTF8

    Write-Host ''
    Write-Host 'Demo pubblica avviata correttamente.' -ForegroundColor Green
    Write-Host "URL:      $publicUrl"
    Write-Host "Utente:   $AccessUsername"
    Write-Host "Password: $AccessPassword"
    Write-Host ''
    Write-Host 'Lascia accesi XAMPP MySQL, Ollama e questo PC.'
    Write-Host 'Per fermare la demo: .\scripts\Stop-PublicDemo.ps1'
} catch {
    if ($null -ne $tunnelProcess -and -not $tunnelProcess.HasExited) {
        Stop-Process -Id $tunnelProcess.Id -Force -ErrorAction SilentlyContinue
    }
    if ($null -ne $phpProcess -and -not $phpProcess.HasExited) {
        Stop-Process -Id $phpProcess.Id -Force -ErrorAction SilentlyContinue
    }
    throw
}
