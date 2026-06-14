param(
    [string]$RepoPath = "C:\www\CarrotHome",
    [string]$Branch = "main",
    [string]$Remote = "origin",
    [bool]$StashLocalChanges = $true
)

$ErrorActionPreference = "Stop"

if (!(Test-Path $RepoPath)) {
    throw "Repo path does not exist: $RepoPath"
}

Set-Location $RepoPath

if (!(Test-Path ".git")) {
    throw "Not a git repository: $RepoPath"
}

$repoFullPath = (Resolve-Path $RepoPath).Path
git config --global --add safe.directory $repoFullPath

$dirty = git status --porcelain
if ($dirty -and $StashLocalChanges) {
    $stamp = Get-Date -Format "yyyyMMdd-HHmmss"
    git stash push --include-untracked -m "auto-deploy-stash-$stamp"
}

git fetch --prune $Remote

$remoteRef = "$Remote/$Branch"
git rev-parse --verify $remoteRef | Out-Null

$localBranchExists = $true
try {
    git rev-parse --verify $Branch | Out-Null
} catch {
    $localBranchExists = $false
}

if ($localBranchExists) {
    git checkout $Branch
} else {
    git checkout -b $Branch --track $remoteRef
}

git reset --hard $remoteRef

Write-Host "Deploy synced to $remoteRef at commit:"
git --no-pager log -1 --oneline

# Them lenh restart service/web server o day neu can.
# Vi du:
# iisreset
# Restart-Service -Name "YourServiceName"
