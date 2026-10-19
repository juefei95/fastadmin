param([switch]$SyncInstalledFiles)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$pluginRoot = Join-Path $projectRoot 'addons\pawchess'
$containerName = 'ld-backend-paw-chess-debug-web'
$containerProjectRoot = '/app'
if (-not (Test-Path -LiteralPath (Join-Path $pluginRoot 'info.ini'))) { throw 'pawchess plugin directory not found' }

function Invoke-ContainerPhp {
    param([Parameter(Mandatory = $true)][string[]]$PhpArguments)

    & docker exec $containerName php @PhpArguments
    if ($LASTEXITCODE -ne 0) {
        throw "PHP command failed in container (exit code $LASTEXITCODE): php $($PhpArguments -join ' ')"
    }
}

Push-Location $projectRoot
try {
    if ($SyncInstalledFiles) {
        # FastAdmin move copies the addon files; it does not remove runtime files.
        Invoke-ContainerPhp -PhpArguments @('think', 'addon', '--name', 'pawchess', '--action', 'move')
    }
    $files = Get-ChildItem -LiteralPath $pluginRoot -Recurse -File -Filter '*.php'
    foreach ($file in $files) {
        $relativePath = $file.FullName.Substring($projectRoot.Length).TrimStart([char[]]@([char]92, [char]47)).Replace('\', '/')
        $containerPath = "$containerProjectRoot/$relativePath"
        Invoke-ContainerPhp -PhpArguments @('-l', $containerPath)
    }
    Invoke-ContainerPhp -PhpArguments @('think', 'addon', '--name', 'pawchess', '--action', 'package')
} finally { Pop-Location }
