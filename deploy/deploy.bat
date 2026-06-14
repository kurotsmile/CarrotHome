@echo off
setlocal

REM Sua duong dan nay thanh thu muc code tren PC Windows cua ban.
set "REPO_PATH=C:\www\CarrotHome"
set "BRANCH=main"
set "SCRIPT_DIR=%~dp0"

powershell -NoProfile -ExecutionPolicy Bypass -File "%SCRIPT_DIR%pull-latest.ps1" -RepoPath "%REPO_PATH%" -Branch "%BRANCH%" -StashLocalChanges:$true

if errorlevel 1 (
    echo Deploy failed.
    exit /b 1
)

echo Deploy completed.
exit /b 0
