@echo off
setlocal
if not exist "%~dp0analyze_code_config.bat" (
    echo ERROR: Copy analyze_code_config.example.bat to analyze_code_config.bat and configure local paths.
    exit /b 1
)
call "%~dp0analyze_code_config.bat"
if errorlevel 1 exit /b %ERRORLEVEL%
pushd "%~dp0.."
if errorlevel 1 exit /b %ERRORLEVEL%
echo Running PHP code style fixer...
"%CLI_ANALYZER_PATH%\venv\Scripts\python.exe" "%CLI_ANALYZER_PATH%\php_fixer.py" --path "." --rules "code_analysis_rules.json" %* <NUL
set "FIX_RESULT=%ERRORLEVEL%"
popd
exit /b %FIX_RESULT%
