@echo off
chcp 65001 >nul
title Himalayan Homestay Bookings — Git Manager
color 0A

:: =====================================================================
:: Smart Git Manager — himalayan-homestay-bookings (Plugin)
:: Reusable interactive script for init, commit, branch, push, pull.
:: Supports GitHub/GitLab auth via Personal Access Token.
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
    for /f "tokens=*" %%r in ('git remote get-url origin 2^>nul') do set REMOTE_URL=%%r
    if defined REMOTE_URL (
        echo    [✓] Remote: %REMOTE_URL%
    ) else (
        echo    [✗] No remote configured
    )
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
echo    7.  Connect Remote (with username/token login)
echo    8.  Push to Remote
echo    9.  Pull from Remote
echo   10.  Save Credentials (store login permanently)
echo   11.  View Remote Info
echo   12.  Exit
echo.
echo  ─────────────────────────────────────────────────────────────
echo.

set /p CHOICE="  Select an option (1-12): "

if "%CHOICE%"=="1" goto INIT
if "%CHOICE%"=="2" goto STATUS
if "%CHOICE%"=="3" goto COMMIT
if "%CHOICE%"=="4" goto NEW_BRANCH
if "%CHOICE%"=="5" goto SWITCH_BRANCH
if "%CHOICE%"=="6" goto LOG
if "%CHOICE%"=="7" goto CONNECT_REMOTE
if "%CHOICE%"=="8" goto PUSH
if "%CHOICE%"=="9" goto PULL
if "%CHOICE%"=="10" goto SAVE_CREDS
if "%CHOICE%"=="11" goto REMOTE_INFO
if "%CHOICE%"=="12" goto EXIT

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
echo # ChatGPT images ^(not code^)
echo ChatGPT Image*.png
echo.
echo # Node
echo node_modules/
echo package-lock.json
echo.
echo # Git manager itself
echo git-manage.bat
) > .gitignore

echo  [*] Running git init...
git init

echo  [*] Adding all files...
git add -A

set /p INIT_MSG="  Commit message [default: init: Himalayan Homestay Bookings v2.0 - Enterprise booking system with advanced pricing, calendar, email notifications, and extra services]: "
if "%INIT_MSG%"=="" set "INIT_MSG=init: Himalayan Homestay Bookings v2.0 - Enterprise booking system with advanced pricing, calendar, email notifications, and extra services"

git commit -m "%INIT_MSG%"

echo.
echo  [✓] Repository initialized and first commit done!
echo.
echo  ─────────────────────────────────────────────────────────────
echo  TIP: Now use Option 7 to connect a GitHub/GitLab remote.
echo  ─────────────────────────────────────────────────────────────
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
if /i "%PUSH_NOW%"=="y" goto PUSH_AFTER_COMMIT

pause
goto MENU

:PUSH_AFTER_COMMIT
for /f "tokens=*" %%b in ('git branch --show-current 2^>nul') do set PUSH_BRANCH=%%b
echo  [*] Pushing to origin/%PUSH_BRANCH%...
git push -u origin %PUSH_BRANCH%
echo.
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
:: 7. CONNECT REMOTE (with username/password or token)
:: =====================================================================
:CONNECT_REMOTE
cls
echo.
echo  ── Connect Remote Repository ──
echo.
echo  Choose your Git provider:
echo    1. GitHub
echo    2. GitLab
echo    3. Bitbucket
echo    4. Custom URL
echo.

set /p PROVIDER="  Select provider (1-4) [1]: "
if "%PROVIDER%"=="" set PROVIDER=1

echo.
echo  ─────────────────────────────────────────────────────────────
echo  NOTE: GitHub no longer allows passwords. Use a Personal
echo  Access Token (PAT) instead. Generate one at:
echo    GitHub:    https://github.com/settings/tokens
echo    GitLab:    Settings ^> Access Tokens
echo    Bitbucket: Settings ^> App Passwords
echo  ─────────────────────────────────────────────────────────────
echo.

set /p GIT_USERNAME="  Enter your username: "
if "%GIT_USERNAME%"=="" (
    echo  [!] Username cannot be empty.
    pause
    goto MENU
)

set /p GIT_TOKEN="  Enter your Password / Personal Access Token: "
if "%GIT_TOKEN%"=="" (
    echo  [!] Token cannot be empty.
    pause
    goto MENU
)

if "%PROVIDER%"=="4" (
    set /p CUSTOM_HOST="  Enter host (e.g. git.myserver.com): "
    set GIT_HOST=%CUSTOM_HOST%
) else if "%PROVIDER%"=="3" (
    set GIT_HOST=bitbucket.org
) else if "%PROVIDER%"=="2" (
    set GIT_HOST=gitlab.com
) else (
    set GIT_HOST=github.com
)

set /p REPO_NAME="  Enter repository name (e.g. username/repo-name): "
if "%REPO_NAME%"=="" (
    echo  [!] Repo name cannot be empty.
    pause
    goto MENU
)

set FULL_URL=https://%GIT_USERNAME%:%GIT_TOKEN%@%GIT_HOST%/%REPO_NAME%.git

echo.
echo  [*] Remote URL: https://%GIT_USERNAME%:****@%GIT_HOST%/%REPO_NAME%.git
echo.

:: Check if origin already exists
git remote get-url origin >nul 2>&1
if %ERRORLEVEL%==0 (
    echo  [!] Remote 'origin' already exists. Updating URL...
    git remote set-url origin %FULL_URL%
) else (
    git remote add origin %FULL_URL%
)

echo  [✓] Remote origin connected!
echo.

set /p PUSH_INIT="  Push current branch to remote now? (y/n) [y]: "
if /i "%PUSH_INIT%"=="n" (
    pause
    goto MENU
)

for /f "tokens=*" %%b in ('git branch --show-current 2^>nul') do set CUR_BRANCH=%%b
echo  [*] Pushing %CUR_BRANCH% to origin...
git push -u origin %CUR_BRANCH%

echo.
echo  [✓] Push complete! Your code is now on %GIT_HOST%.
echo.
pause
goto MENU

:: =====================================================================
:: 8. PUSH
:: =====================================================================
:PUSH
cls
echo.
echo  ── Push to Remote ──
echo.

:: Check if remote exists
git remote get-url origin >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo  [!] No remote configured. Use Option 7 first.
    echo.
    pause
    goto MENU
)

for /f "tokens=*" %%b in ('git branch --show-current 2^>nul') do set PUSH_BRANCH=%%b
echo  Current branch: %PUSH_BRANCH%
echo.

set /p PUSH_CONFIRM="  Push to origin/%PUSH_BRANCH%? (y/n) [y]: "
if /i "%PUSH_CONFIRM%"=="n" goto MENU

echo  [*] Pushing...
git push -u origin %PUSH_BRANCH%

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo  [!] Push failed. If authentication failed, try:
    echo      Option 7  — Reconnect remote with new token
    echo      Option 10 — Save credentials permanently
    echo.
) else (
    echo.
    echo  [✓] Pushed successfully!
    echo.
)

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

:: Check if remote exists
git remote get-url origin >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo  [!] No remote configured. Use Option 7 first.
    echo.
    pause
    goto MENU
)

for /f "tokens=*" %%b in ('git branch --show-current 2^>nul') do set PULL_BRANCH=%%b
echo  Current branch: %PULL_BRANCH%
echo.

set /p PULL_CONFIRM="  Pull from origin/%PULL_BRANCH%? (y/n) [y]: "
if /i "%PULL_CONFIRM%"=="n" goto MENU

echo  [*] Pulling...
git pull origin %PULL_BRANCH%

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo  [!] Pull failed. You may have merge conflicts or auth issues.
    echo.
) else (
    echo.
    echo  [✓] Pull complete!
    echo.
)

pause
goto MENU

:: =====================================================================
:: 10. SAVE CREDENTIALS
:: =====================================================================
:SAVE_CREDS
cls
echo.
echo  ── Save Git Credentials ──
echo.
echo  This stores your login so you don't need to re-enter
echo  your token on every push/pull.
echo.
echo  Options:
echo    1. Store permanently (saved to disk)
echo    2. Cache for 8 hours (in memory only)
echo    3. Use Windows Credential Manager (recommended)
echo.

set /p CRED_CHOICE="  Select (1-3) [3]: "
if "%CRED_CHOICE%"=="" set CRED_CHOICE=3

if "%CRED_CHOICE%"=="1" (
    git config --global credential.helper store
    echo.
    echo  [✓] Credentials will be stored permanently in ~/.git-credentials
    echo  [!] Note: Token is saved in plain text. Use option 3 for security.
) else if "%CRED_CHOICE%"=="2" (
    git config --global credential.helper "cache --timeout=28800"
    echo.
    echo  [✓] Credentials will be cached for 8 hours.
) else (
    git config --global credential.helper manager
    echo.
    echo  [✓] Windows Credential Manager enabled.
    echo      Your token will be securely stored by Windows.
)

echo.
echo  Next time you push/pull, enter your credentials once and
echo  they'll be remembered automatically.
echo.
pause
goto MENU

:: =====================================================================
:: 11. REMOTE INFO
:: =====================================================================
:REMOTE_INFO
cls
echo.
echo  ── Remote Repository Info ──
echo.
echo  Configured remotes:
git remote -v
echo.
echo  ─────────────────────────────
echo.
echo  Credential helper:
git config --global credential.helper
echo.
echo  ─────────────────────────────
echo.
echo  User config:
echo    Name:  
git config user.name
echo    Email: 
git config user.email
echo.
echo  ─────────────────────────────
echo.
set /p SET_USER="  Set/update git user name and email? (y/n) [n]: "
if /i "%SET_USER%"=="y" (
    echo.
    set /p NEW_NAME="  Enter your name: "
    set /p NEW_EMAIL="  Enter your email: "
    git config --global user.name "%NEW_NAME%"
    git config --global user.email "%NEW_EMAIL%"
    echo.
    echo  [✓] Git user updated!
)
echo.
pause
goto MENU

:: =====================================================================
:: 12. EXIT
:: =====================================================================
:EXIT
echo.
echo  Goodbye!
echo.
exit /b 0
