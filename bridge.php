<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * bridge.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('mod/lumen10voiceaccess:manage', context_system::instance());

// Download only. Never start a process on the server or a participant's device.
$parts = parse_url($CFG->wwwroot);
$origin = $parts['scheme'] . '://' . $parts['host'];
if (!empty($parts['port'])) {
    $origin .= ':' . $parts['port'];
}
$origins = base64_encode(json_encode([$origin], JSON_UNESCAPED_SLASHES));
$script = <<<'POWERSHELL'
# Lumen10 VoiceAccess - optional, manually started Windows speech helper.
# Copyright 2026 Carlos Antonio de Oliveira Piquet. GNU GPL v3 or later.
# Review before running. Close this window or press Ctrl+C to stop.
# No browser-policy changes, persistence, administrator rights or execution-policy bypass.
$ErrorActionPreference = 'Stop'
$AllowedOrigins = @([Text.Encoding]::UTF8.GetString([Convert]::FromBase64String('__ORIGINS__')) | ConvertFrom-Json)
Add-Type -AssemblyName System.Speech
$VoiceAccessSynth = New-Object System.Speech.Synthesis.SpeechSynthesizer
$VoiceAccessSynth.SetOutputToDefaultAudioDevice()
$VoiceAccessListener = New-Object System.Net.HttpListener
$VoiceAccessListener.Prefixes.Add('http://127.0.0.1:10110/')
try {
    $VoiceAccessListener.Start()
    Write-Host 'Lumen10 VoiceAccess speech helper is running. Close this window to stop.'
    while ($VoiceAccessListener.IsListening) {
        $VoiceAccessContext = $VoiceAccessListener.GetContext()
        $VoiceAccessRequest = $VoiceAccessContext.Request
        $VoiceAccessResponse = $VoiceAccessContext.Response
        try {
            $VoiceAccessOrigin = [string]$VoiceAccessRequest.Headers['Origin']
            if ($AllowedOrigins -cnotcontains $VoiceAccessOrigin) {
                $VoiceAccessResponse.StatusCode = 403
                continue
            }
            $VoiceAccessResponse.Headers.Add('Access-Control-Allow-Origin', $VoiceAccessOrigin)
            $VoiceAccessResponse.Headers.Add('Vary', 'Origin')
            $VoiceAccessResponse.Headers.Add('Cache-Control', 'no-store')
            $VoiceAccessResponse.Headers.Add('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            $VoiceAccessResponse.Headers.Add('Access-Control-Allow-Headers', 'Content-Type')
            $VoiceAccessResponse.Headers.Add('Access-Control-Allow-Private-Network', 'true')
            if ($VoiceAccessRequest.HttpMethod -eq 'OPTIONS') {
                $VoiceAccessResponse.StatusCode = 204
                continue
            }
            $VoiceAccessPath = $VoiceAccessRequest.Url.AbsolutePath.ToLowerInvariant()
            if ($VoiceAccessPath -eq '/speak' -and $VoiceAccessRequest.HttpMethod -eq 'POST') {
                if ($VoiceAccessRequest.ContentLength64 -lt 0 -or $VoiceAccessRequest.ContentLength64 -gt 8192) {
                    $VoiceAccessResponse.StatusCode = 413
                    continue
                }
                $VoiceAccessReader = New-Object IO.StreamReader($VoiceAccessRequest.InputStream, [Text.Encoding]::UTF8)
                try { $VoiceAccessBody = $VoiceAccessReader.ReadToEnd() | ConvertFrom-Json }
                finally { $VoiceAccessReader.Dispose() }
                if ($VoiceAccessBody.text -isnot [string] -or $VoiceAccessBody.text.Length -gt 2000) {
                    $VoiceAccessResponse.StatusCode = 400
                    continue
                }
                $VoiceAccessSynth.SpeakAsyncCancelAll()
                $VoiceAccessSynth.SpeakAsync($VoiceAccessBody.text) | Out-Null
            } elseif ($VoiceAccessPath -eq '/stop' -and $VoiceAccessRequest.HttpMethod -eq 'POST') {
                $VoiceAccessSynth.SpeakAsyncCancelAll()
            } elseif ($VoiceAccessPath -notin @('/poll', '/health') -or $VoiceAccessRequest.HttpMethod -ne 'GET') {
                $VoiceAccessResponse.StatusCode = 404
                continue
            }
            $VoiceAccessPayload = @{ok = $true; hardware = $true; speaking = ($VoiceAccessSynth.State -eq 'Speaking'); commands = @()} | ConvertTo-Json -Compress
            $VoiceAccessBytes = [Text.Encoding]::UTF8.GetBytes($VoiceAccessPayload)
            $VoiceAccessResponse.ContentType = 'application/json; charset=utf-8'
            $VoiceAccessResponse.OutputStream.Write($VoiceAccessBytes, 0, $VoiceAccessBytes.Length)
        } catch {
            $VoiceAccessResponse.StatusCode = 400
        } finally {
            $VoiceAccessResponse.Close()
        }
    }
} finally {
    $VoiceAccessListener.Stop()
    $VoiceAccessListener.Close()
    $VoiceAccessSynth.SpeakAsyncCancelAll()
    $VoiceAccessSynth.Dispose()
}
POWERSHELL;

header('Content-Type: application/octet-stream; charset=utf-8');
header('Content-Disposition: attachment; filename="Lumen10-VoiceAccess-Speech-Helper.ps1"');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo str_replace('__ORIGINS__', $origins, $script);
