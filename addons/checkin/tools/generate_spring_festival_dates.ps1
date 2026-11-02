$dates = 2026..2100 | ForEach-Object -Parallel {
    $year = $_
    $url = "https://www.hko.gov.hk/en/gts/time/calendar/text/files/T${year}e.txt"
    $body = (Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 20).Content
    if ($body -notmatch "(?m)^${year}/(\d{1,2})/(\d{1,2})\s+1st Lunar Month\b") {
        throw "Missing Lunar New Year for $year in $url"
    }
    '{0} => ''{0}-{1:D2}-{2:D2}'',' -f $year, [int]$Matches[1], [int]$Matches[2]
} -ThrottleLimit 6

if ($dates.Count -ne 75) { throw "Expected 75 dates, got $($dates.Count)" }
$output = Join-Path $PSScriptRoot '..\library\spring_festival_dates.php'
$lines = @(
    '<?php',
    '// Lunar New Year dates from Hong Kong Observatory Gregorian-Lunar calendar tables.',
    '// Source: https://www.hko.gov.hk/en/gts/time/conversion1_text.htm',
    'return ['
) + ($dates | Sort-Object) + @('];')
[System.IO.File]::WriteAllText((Join-Path $PSScriptRoot '..\library\spring_festival_dates.php'), ($lines -join "`n") + "`n", [System.Text.UTF8Encoding]::new($false))
Write-Output "Wrote $($dates.Count) Lunar New Year dates to $output"
