@echo off
REM Integration tests - the Slim app in-process with temp files, no web server needed.
call "%~dp0tests.bat" --testsuite Integration %*
exit /b %ERRORLEVEL%
