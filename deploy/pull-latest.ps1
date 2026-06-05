param(
    [string]$RepoPath = "C:\www\CarrotHome",
    [string]$Branch = "main",
    [string]$Remote = "origin"
)

$ErrorActionPreference = "Stop"

if (!(Test-Path $RepoPath)) {
    throw "Repo path does not exist: $RepoPath"
}

Set-Location $RepoPath

if (!(Test-Path ".git")) {
    throw "Not a git repository: $RepoPath"
}

git fetch $Remote $Branch
git checkout $Branch
git pull --ff-only $Remote $Branch

# Them lenh restart service/web server o day neu can.
# Vi du:
# iisreset
# Restart-Service -Name "YourServiceName"
