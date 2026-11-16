@echo off
setlocal
REM Runs PHPUnit with the PHP from tests_config.bat. Without arguments: all suites.
REM Extra arguments are passed to PHPUnit (run_tests.bat / run_integration_tests.bat pick a suite).
if not exist "%~dp0tests_config.bat" (
    echo ERROR: tools\tests_config.bat not found.
    echo Copy tools\tests_config.example.bat to tools\tests_config.bat and set PHP_EXE.
    exit /b 1
)
call "%~dp0tests_config.bat"
set "XDEBUG_MODE=off"
set "PHPUNIT_WATCHER_LIMITS="
set "PHP_WATCHER_LIMITS="
REM Windows has no pcntl: PHP's execution limit supplies the hard stop, including I/O waits.
if "%TICKETS_WATCHER_COMMAND_RUN%"=="1" (
    set "PHP_WATCHER_LIMITS=-d max_execution_time=120"
    set "PHPUNIT_WATCHER_LIMITS=--timeout 500"
)
pushd "%~dp0.."
"%PHP_EXE%" %PHP_WATCHER_LIMITS% vendor\phpunit\phpunit\phpunit --debug --colors=always %PHPUNIT_WATCHER_LIMITS% %*
set "TESTRESULT=%ERRORLEVEL%"
popd
endlocal & exit /b %TESTRESULT%
