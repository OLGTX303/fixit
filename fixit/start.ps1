$port = 3000
$url  = "http://localhost:$port"
$root = $PSScriptRoot

Write-Host "Starting FixIt server on $url ..." -ForegroundColor Cyan

# Kill any process already using the port
$old = netstat -ano | Select-String ":$port\s" | ForEach-Object {
    ($_ -split '\s+')[-1]
} | Sort-Object -Unique
foreach ($pid in $old) {
    if ($pid -match '^\d+$' -and $pid -ne '0') {
        Stop-Process -Id $pid -Force -ErrorAction SilentlyContinue
    }
}

# Launch Node in the background
$node = Start-Process -FilePath "node" `
    -ArgumentList "$root\server.js" `
    -WorkingDirectory $root `
    -PassThru `
    -WindowStyle Hidden

Write-Host "Server PID: $($node.Id)" -ForegroundColor Gray

# Wait until the port responds (up to 10 s)
$ready = $false
for ($i = 0; $i -lt 20; $i++) {
    Start-Sleep -Milliseconds 500
    try {
        $r = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 1 -ErrorAction Stop
        if ($r.StatusCode -eq 200) { $ready = $true; break }
    } catch {}
}

if ($ready) {
    Write-Host "Opening browser..." -ForegroundColor Green
    Start-Process $url
} else {
    Write-Host "Server did not respond in time. Check Node.js is installed." -ForegroundColor Red
}
