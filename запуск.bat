@echo off
chcp 65001 >nul
setlocal EnableExtensions
cd /d "%~dp0"
title ELV October CMS — запуск

set "HOST=127.0.0.1"
set "PORT=8080"
set "URL=http://%HOST%:%PORT%/"
set "ADMIN=%URL%admin"

echo.
echo === Библиотека 2026 на October CMS ===
echo Каталог: %CD%
echo.

rem ---------- 1. PHP ----------
set "PHP="
for /f "delims=" %%P in ('where php 2^>nul') do if not defined PHP set "PHP=%%P"

if not defined PHP for /d %%D in ("C:\laragon\bin\php\php-8.3*") do (
    if not defined PHP if exist "%%D\php.exe" set "PHP=%%D\php.exe"
)
if not defined PHP for /d %%D in ("C:\laragon\bin\php\php-8.4*") do (
    if not defined PHP if exist "%%D\php.exe" set "PHP=%%D\php.exe"
)
if not defined PHP for /d %%D in ("C:\laragon\bin\php\php-*") do (
    if not defined PHP if exist "%%D\php.exe" set "PHP=%%D\php.exe"
)

if not defined PHP (
    echo [ОШИБКА] PHP не найден: нет в PATH и в C:\laragon\bin\php.
    echo Поставьте PHP 8.3 или добавьте каталог PHP в PATH и запустите снова.
    echo.
    pause
    exit /b 1
)

set "PHPVER=?"
"%PHP%" -r "echo PHP_VERSION;" > "%TEMP%\elv_php_version.txt" 2>nul
if exist "%TEMP%\elv_php_version.txt" (
    set /p PHPVER=<"%TEMP%\elv_php_version.txt"
    del "%TEMP%\elv_php_version.txt" >nul 2>&1
)
echo PHP:    %PHPVER%  (%PHP%)

rem ---------- 2. MySQL ----------
call :port_open 3306
if not errorlevel 1 (
    echo MySQL:  порт 3306 отвечает
    goto php_ready
)

echo MySQL:  порт 3306 не отвечает — пробую поднять mysqld из Laragon
set "MYSQLD="
set "MYINI="
for /d %%D in ("C:\laragon\bin\mysql\mysql-*") do (
    if not defined MYSQLD if exist "%%D\bin\mysqld.exe" set "MYSQLD=%%D\bin\mysqld.exe"
    if not defined MYINI if exist "%%D\my.ini" set "MYINI=%%D\my.ini"
)

if not defined MYSQLD (
    echo [ВНИМАНИЕ] mysqld.exe не найден. Запустите MySQL вручную (Laragon)
    echo             и перезапустите этот файл.
    goto php_ready
)

if defined MYINI (
    start "ELV MySQL" /min "%MYSQLD%" --defaults-file="%MYINI%"
) else (
    start "ELV MySQL" /min "%MYSQLD%" --console
)

set "MYSQL_WAIT=0"
:mysql_wait
call :port_open 3306
if not errorlevel 1 (
    echo MySQL:  поднялся
    goto php_ready
)
set /a MYSQL_WAIT+=1
if %MYSQL_WAIT% geq 15 (
    echo [ВНИМАНИЕ] MySQL не поднялся за 30 секунд — сайт ответит ошибкой БД.
    echo             Проверьте Laragon и лог storage\logs\laravel.log.
    goto php_ready
)
timeout /t 2 /nobreak >nul 2>nul
goto mysql_wait

:php_ready
rem ---------- 3. Сервер ----------
call :port_open %PORT%
if not errorlevel 1 (
    echo Сервер: уже слушает %HOST%:%PORT%
    goto open
)

echo Сервер: запускаю php artisan serve (окно свёрнуто)
start "ELV October %PORT%" /min "%PHP%" artisan serve --host=%HOST% --port=%PORT%

set "SERVER_WAIT=0"
set "HAS_CURL="
where curl.exe >nul 2>&1 && set "HAS_CURL=1"
:server_wait
if defined HAS_CURL (
    curl.exe -fsS -o nul --max-time 2 "%URL%health" >nul 2>&1
) else (
    powershell -NoProfile -Command "try{(New-Object Net.WebClient).DownloadString('%URL%health')|Out-Null;exit 0}catch{exit 1}" >nul 2>&1
)
if not errorlevel 1 (
    echo Сервер: /health ответил 200
    goto open
)
set /a SERVER_WAIT+=1
if %SERVER_WAIT% geq 20 (
    echo [ВНИМАНИЕ] /health не ответил за 40 секунд.
    echo             Смотрите окно «ELV October %PORT%» и storage\logs\laravel.log.
    if not "%~1"=="/nobrowser" pause
    goto open
)
timeout /t 2 /nobreak >nul 2>nul
goto server_wait

:open
echo.
echo Сайт:    %URL%
echo Админка: %ADMIN%   (логин admin, пароль — .local\admin-credentials.md)
echo Health:  %URL%health
echo.
echo Остановить: закройте свёрнутые окна «ELV October %PORT%» и «ELV MySQL».
echo.
if /i "%~1"=="/nobrowser" (
    echo Браузер не открываю: ключ /nobrowser.
    exit /b 0
)
start "" "%URL%"
echo Окно можно закрыть — сервер продолжит работать.
timeout /t 5 /nobreak >nul 2>nul
exit /b 0

rem ---------- подпрограммы ----------
:port_open
powershell -NoProfile -Command "$c=New-Object Net.Sockets.TcpClient; try{$c.Connect('127.0.0.1',%1);exit 0}catch{exit 1}finally{$c.Dispose()}" >nul 2>&1
exit /b %errorlevel%
