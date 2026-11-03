<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('local/lumen10_voiceaccess:manage', context_system::instance());

$origin = rtrim((string)$CFG->wwwroot, '/');
$host = parse_url($origin, PHP_URL_HOST) ?: 'cursos.lumen10.com.br';
$rootdomain = preg_replace('/^(?:cursos\.|www\.)/i', '', $host);

$script = <<<'POWERSHELL'
# Lumen10 VoiceAccess — Ativador Autonomo de Microfone, Caixa de Som e Robo de Voz do Aparelho
# Copyright 2026 Carlos Antonio de Oliveira Piquet. GPL-3.0-or-later.
$ErrorActionPreference = 'SilentlyContinue'
$Origins = @('__ORIGIN__', 'https://__HOST__', 'https://__ROOTDOMAIN__', 'https://www.__ROOTDOMAIN__', 'https://cursos.__ROOTDOMAIN__', 'http://localhost', 'http://127.0.0.1') | Select-Object -Unique

Write-Host "==================================================================" -ForegroundColor Cyan
Write-Host " LUMEN10 ACESSO UNIVERSAL - ROBO DE VOZ E HARDWARE DO APARELHO" -ForegroundColor Cyan
Write-Host "==================================================================" -ForegroundColor Cyan

# 1. Liberar Microfone, Caixa de Som (Autoplay) e Rede Local (127.0.0.1) nas politicas do Chrome, Edge e Brave
$PolicyRoots = @(
    'HKCU:\Software\Policies\Google\Chrome',
    'HKCU:\Software\Policies\Microsoft\Edge',
    'HKCU:\Software\Policies\BraveSoftware\Brave',
    'HKLM:\Software\Policies\Google\Chrome',
    'HKLM:\Software\Policies\Microsoft\Edge',
    'HKLM:\Software\Policies\BraveSoftware\Brave'
)

foreach ($Root in $PolicyRoots) {
    New-Item -Path $Root -Force | Out-Null
    Set-ItemProperty -Path $Root -Name 'AudioCaptureAllowed' -Value 1 -Type DWord -Force
    Set-ItemProperty -Path $Root -Name 'AutoplayAllowed' -Value 1 -Type DWord -Force
    Set-ItemProperty -Path $Root -Name 'InsecurePrivateNetworkRequestsAllowed' -Value 1 -Type DWord -Force
    foreach ($Sub in @('AudioCaptureAllowedUrls', 'AutoplayAllowlist', 'InsecurePrivateNetworkRequestsAllowedForUrls')) {
        $SubKey = Join-Path $Root $Sub
        New-Item -Path $SubKey -Force | Out-Null
        $Index = 1
        foreach ($Url in $Origins) {
            Set-ItemProperty -Path $SubKey -Name "$Index" -Value $Url -Type String -Force
            $Index++
        }
    }
}
Write-Host "[OK] Microfone, Caixa de Som e Ponte Local liberados automaticamente sem popup de permissao." -ForegroundColor Green

# 2. Instalar e iniciar o Robo de Voz Nativo do Aparelho (127.0.0.1:10110) usando hardware de som do Windows
Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -like '*Lumen10VoiceAccess*' } | ForEach-Object {
    Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
}
Start-Sleep -Milliseconds 400

$AppDir = Join-Path $env:LOCALAPPDATA 'Lumen10VoiceAccess'
New-Item -Path $AppDir -ItemType Directory -Force | Out-Null
$DaemonPath = Join-Path $AppDir 'lumen10-hardware-robot.ps1'

$DaemonCode = @'
$ErrorActionPreference = 'SilentlyContinue'
Add-Type -AssemblyName System.Speech
$Synth = New-Object System.Speech.Synthesis.SpeechSynthesizer
$Synth.SetOutputToDefaultAudioDevice()
try {
    $PtVoice = $Synth.GetInstalledVoices() | Where-Object { $_.VoiceInfo.Culture.Name -like 'pt*' } | Select-Object -First 1
    if ($PtVoice) { $Synth.SelectVoice($PtVoice.VoiceInfo.Name) }
} catch {}

$Listener = New-Object System.Net.HttpListener
$Listener.Prefixes.Add('http://127.0.0.1:10110/')
$Listener.Start()

while ($Listener.IsListening) {
    $Context = $Listener.GetContext()
    $Req = $Context.Request
    $Res = $Context.Response
    $Res.Headers.Add('Access-Control-Allow-Origin', '*')
    $Res.Headers.Add('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
    $Res.Headers.Add('Access-Control-Allow-Headers', 'Content-Type, Access-Control-Request-Private-Network')
    $Res.Headers.Add('Access-Control-Allow-Private-Network', 'true')
    if ($Req.HttpMethod -eq 'OPTIONS') {
        $Res.StatusCode = 204
        $Res.Close()
        continue
    }
    $Path = $Req.Url.AbsolutePath.ToLowerInvariant()
    $IsSpeaking = ($Synth.State -eq [System.Speech.Synthesis.SynthesizerState]::Speaking)
    $Payload = (@{ ok = $true; hardware = $true; speaking = $IsSpeaking; commands = @() } | ConvertTo-Json -Compress)
    if ($Path -eq '/speak' -and $Req.HttpMethod -eq 'POST') {
        $Reader = New-Object System.IO.StreamReader($Req.InputStream, [System.Text.Encoding]::UTF8)
        $Body = $Reader.ReadToEnd() | ConvertFrom-Json
        if ($Body.text) {
            $Synth.SpeakAsyncCancelAll()
            $Synth.SpeakAsync([string]$Body.text) | Out-Null
        }
    } elseif ($Path -eq '/stop' -and $Req.HttpMethod -eq 'POST') {
        $Synth.SpeakAsyncCancelAll()
    }
    $Bytes = [System.Text.Encoding]::UTF8.GetBytes($Payload)
    $Res.ContentType = 'application/json; charset=utf-8'
    $Res.OutputStream.Write($Bytes, 0, $Bytes.Length)
    $Res.Close()
}
'@

Set-Content -Path $DaemonPath -Value $DaemonCode -Encoding UTF8 -Force
$RunKey = 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Run'
$Cmd = "powershell.exe -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$DaemonPath`""
Set-ItemProperty -Path $RunKey -Name 'Lumen10VoiceAccessRobot' -Value $Cmd -Type String -Force
Start-Process powershell.exe -ArgumentList "-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$DaemonPath`"" -WindowStyle Hidden
Write-Host "[OK] Robo de Voz do Aparelho ativo em 127.0.0.1:10110 e configurado na inicializacao do sistema." -ForegroundColor Green
POWERSHELL;

$script = str_replace(
    ['__ORIGIN__', '__HOST__', '__ROOTDOMAIN__'],
    [$origin, $host, $rootdomain],
    $script
);

header('Content-Type: application/octet-stream; charset=utf-8');
header('Content-Disposition: attachment; filename="Lumen10-Ativar-Robo-Aparelho.ps1"');
header('Cache-Control: no-store');
echo $script;
