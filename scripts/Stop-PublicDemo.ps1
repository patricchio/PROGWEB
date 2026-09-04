[CmdletBinding()]
param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$statePath = Join-Path $projectRoot '.runtime\public-demo.json'

if (-not (Test-Path -LiteralPath $statePath)) {
    Write-Host 'Nessuna demo pubblica registrata.'
    exit 0
}

$state = Get-Content -Raw -LiteralPath $statePath | ConvertFrom-Json
if ($state.active -ne $true) {
    Write-Host 'La demo risulta gia arrestata.'
    exit 0
}

$targets = @(
    @{ Id = [int]$state.cloudflaredPid; ExpectedName = 'cloudflared' },
    @{ Id = [int]$state.phpPid; ExpectedName = 'php' }
)

foreach ($target in $targets) {
    $process = Get-Process -Id $target.Id -ErrorAction SilentlyContinue
    if ($null -ne $process -and $process.ProcessName -eq $target.ExpectedName) {
        Stop-Process -Id $process.Id -Force
        Write-Host "Arrestato $($process.ProcessName) (PID $($process.Id))."
    }
}

$state.active = $false
$state | Add-Member -NotePropertyName stoppedAt -NotePropertyValue ((Get-Date).ToString('o')) -Force
$state | ConvertTo-Json | Set-Content -LiteralPath $statePath -Encoding UTF8
Write-Host 'Demo pubblica arrestata.' -ForegroundColor Green
