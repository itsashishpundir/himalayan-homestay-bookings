@echo off
chcp 65001 >nul
title Himalayan Homestay Bookings — Git Manager
color 0A

:: =====================================================================
:: Smart Git Manager — himalayan-homestay-bookings
:: Reusable interactive script for init, commit, branch, and push.
:: =====================================================================

:MENU
cls
echo.
echo  ╔══════════════════════════════════════════════════════════════╗
echo  ║     HIMALAYAN HOMESTAY BOOKINGS — Git Manager               ║
echo  ╚══════════════════════════════════════════════════════════════╝
echo.
echo    Directory: %CD%
echo.

:: Check if git is initialized
if exist ".git" (
    echo    [✓] Git is initialized
    for /f "tokens=*" %%b in ('git branch --show-current 2^>nul') do set CURRENT_BRANCH=%%b
    echo    [✓] Current branch: %CURRENT_BRANCH%
    echo.
) else (
    echo    [✗] Git is NOT initialized
    echo.
)

echo  ─────────────────────────────────────────────────────────────
echo.
echo    1.  Initialize Git (git init + .gitignore + first commit)
echo    2.  View Status (git status)
echo    3.  Add All ^& Commit
echo    4.  Create New Branch
echo    5.  Switch Branch
echo    6.  View Log (last 10 commits)
echo    7.  Push to Remote
echo    8.  Add Remote Origin
echo    9.  Pull from Remote
echo   10.  Exit
echo.
echo  ─────────────────────────────────────────────────────────────
echo.

set /p CHOICE="  Select an option (1-10): "

if "%CHOICE%"=="1" goto INIT
if "%CHOICE%"=="2" goto STATUS
if "%CHOICE%"=="3" goto COMMIT
if "%CHOICE%"=="4" goto NEW_BRANCH
if "%CHOICE%"=="5" goto SWITCH_BRANCH
if "%CHOICE%"=="6" goto LOG
if "%CHOICE%"=="7" goto PUSH
if "%CHOICE%"=="8" goto ADD_REMOTE
if "%CHOICE%"=="9" goto PULL
if "%CHOICE%"=="10" goto EXIT

echo.
echo  [!] Invalid option. Try again.
timeout /t 2 >nul
goto MENU

:: =====================================================================
:: 1. INITIALIZE
:: =====================================================================
:INIT
cls
echo.
echo  ── Initializing Git Repository ──
echo.

if exist ".git" (
    echo  [!] Git is already initialized in this directory.
    echo.
    pause
    goto MENU
)

:: Create .gitignore
echo  [*] Creating .gitignore...
(
echo # IDE
echo .vscode/
echo .idea/
echo *.code-workspace
echo.
echo # OS
echo Thumbs.db
echo .DS_Store
echo desktop.ini
echo.
echo # Temp
echo *.log
echo *.tmp
echo *.bak
echo *.swp
echo.
echo # ChatGPT images (not code)
echo ChatGPT Image*.png
echo.
echo # Node (if ever used)
echo node_modules/
echo package-lock.json
) > .gitignore

echo  [*] Running git init...
git init

echo  [*] Adding all files...
git add -A

set /p INIT_MSG="  Enter initial commit message [default: Initial commit - Himalayan Homestay Bookings v2.0]: "
if "%INIT_MSG%"=="" set INIT_MSG=Initial commit - Himalayan Homestay Bookings v2.0

git commit -m "%INIT_MSG%"

echo.
echo  [✓] Repository initialized and first commit done!
echo.
pause
goto MENU

:: =====================================================================
:: 2. STATUS
:: =====================================================================
:STATUS
cls
echo.
echo  ── Git Status ──
echo.
git status
echo.
pause
goto MENU

:: =====================================================================
:: 3. ADD ALL & COMMIT
:: =====================================================================
:COMMIT
cls
echo.
echo  ── Add All ^& Commit ──
echo.

git status --short
echo.
echo  ─────────────────────────────────────────────────────────────
echo.
echo  Commit Types:
echo    feat     — New feature
echo    fix      — Bug fix
echo    refactor — Code refactoring
echo    style    — Styling / UI changes
echo    docs     — Documentation
echo    chore    — Maintenance / cleanup
echo    perf     — Performance improvement
echo.

set /p COMMIT_TYPE="  Select commit type [feat]: "
if "%COMMIT_TYPE%"=="" set COMMIT_TYPE=feat

set /p COMMIT_MSG="  Enter commit message: "
if "%COMMIT_MSG%"=="" (
    echo  [!] Commit message cannot be empty.
    pause
    goto MENU
)

echo.
echo  [*] Staging all changes...
git add -A

echo  [*] Committing...
git commit -m "%COMMIT_TYPE%: %COMMIT_MSG%"

echo.
echo  [✓] Committed successfully!
echo.

set /p PUSH_NOW="  Push to remote now? (y/n) [n]: "
if /i "%PUSH_NOW%"=="y" (
    for /f "tokens=*" %%b in ('git branch --show-current 2^>nul') do set PUSH_BRANCH=%%b
    echo  [*] Pushing to origin/%PUSH_BRANCH%...
    git push origin %PUSH_BRANCH%
    echo.
)

pause
goto MENU

:: =====================================================================
:: 4. CREATE NEW BRANCH
:: =====================================================================
:NEW_BRANCH
cls
echo.
echo  ── Create New Branch ──
echo.
echo  Current branches:
git branch
echo.

set /p BRANCH_NAME="  Enter new branch name: "
if "%BRANCH_NAME%"=="" (
    echo  [!] Branch name cannot be empty.
    pause
    goto MENU
)

git checkout -b %BRANCH_NAME%

echo.
echo  [✓] Switched to new branch: %BRANCH_NAME%
echo.
pause
goto MENU

:: =====================================================================
:: 5. SWITCH BRANCH
:: =====================================================================
:SWITCH_BRANCH
cls
echo.
echo  ── Switch Branch ──
echo.
echo  Available branches:
git branch
echo.

set /p SW_BRANCH="  Enter branch name to switch to: "
if "%SW_BRANCH%"=="" (
    echo  [!] Branch name cannot be empty.
    pause
    goto MENU
)

git checkout %SW_BRANCH%

echo.
echo  [✓] Switched to branch: %SW_BRANCH%
echo.
pause
goto MENU

:: =====================================================================
:: 6. LOG
:: =====================================================================
:LOG
cls
echo.
echo  ── Recent Commits (last 10) ──
echo.
git log --oneline --graph --decorate -n 10
echo.
pause
goto MENU

:: =====================================================================
:: 7. PUSH
:: =====================================================================
:PUSH
cls
echo.
echo  ── Push to Remote ──
echo.

for /f "tokens=*" %%b in ('git branch --show-current 2^>nul') do set PUSH_BRANCH=%%b
echo  Current branch: %PUSH_BRANCH%
echo.

set /p PUSH_CONFIRM="  Push to origin/%PUSH_BRANCH%? (y/n) [y]: "
if /i "%PUSH_CONFIRM%"=="n" goto MENU

git push origin %PUSH_BRANCH%

echo.
echo  [✓] Pushed successfully!
echo.
pause
goto MENU

:: =====================================================================
:: 8. ADD REMOTE
:: =====================================================================
:ADD_REMOTE
cls
echo.
echo  ── Add Remote Origin ──
echo.
echo  Current remotes:
git remote -v
echo.

set /p REMOTE_URL="  Enter remote URL (e.g. https://github.com/user/repo.git): "
if "%REMOTE_URL%"=="" (
    echo  [!] URL cannot be empty.
    pause
    goto MENU
)

git remote add origin %REMOTE_URL%

echo.
echo  [✓] Remote origin added: %REMOTE_URL%
echo.
pause
goto MENU

:: =====================================================================
:: 9. PULL
:: =====================================================================
:PULL
cls
echo.
echo  ── Pull from Remote ──
echo.

for /f "tokens=*" %%b in ('git branch --show-current 2^>nul') do set PULL_BRANCH=%%b
echo  Pulling origin/%PULL_BRANCH%...
echo.

git pull origin %PULL_BRANCH%

echo.
echo  [✓] Pull complete!
echo.
pause
goto MENU

:: =====================================================================
:: 10. EXIT
:: =====================================================================
:EXIT
echo.
echo  Goodbye!
echo.
exit /b 0
