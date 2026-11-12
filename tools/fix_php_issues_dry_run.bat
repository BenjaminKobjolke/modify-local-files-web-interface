@echo off
call "%~dp0fix_php_issues.bat" --dry-run
exit /b %ERRORLEVEL%
