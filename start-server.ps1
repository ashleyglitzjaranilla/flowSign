$projectRoot = $PSScriptRoot
$php = 'C:\xampp\php\php.exe'
$uploadTemp = Join-Path $projectRoot 'storage\tmp'
$sessionPath = Join-Path $projectRoot 'storage\sessions'

if (-not (Test-Path -LiteralPath $php)) {
    throw 'XAMPP PHP was not found at C:\xampp\php\php.exe.'
}

New-Item -ItemType Directory -Force -Path $uploadTemp, $sessionPath | Out-Null
& $php `
    -d "upload_tmp_dir=$uploadTemp" `
    -d "sys_temp_dir=$uploadTemp" `
    -d "session.save_path=$sessionPath" `
    -S 127.0.0.1:8000 `
    -t (Join-Path $projectRoot 'public')
