@echo off
REM Unit tests only - no web server needed.
call "%~dp0tests.bat" --testsuite Unit %*
exit /b %ERRORLEVEL%
