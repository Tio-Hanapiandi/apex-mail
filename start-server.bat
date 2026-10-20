@echo off
title ApexMail Collaboration Suite Server
echo ========================================================
echo   ApexMail Collaboration Suite - Modern Mail Server
echo ========================================================

set "PHP_BIN=F:\Backup_SSD\Laragon\bin\php\php-5.4.9-nts-Win32-VC9-x86\php.exe"
if not exist "%PHP_BIN%" set "PHP_BIN=C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe"
if not exist "%PHP_BIN%" set "PHP_BIN=php"

echo Menggunakan PHP: %PHP_BIN%
echo Membuka server lokal di http://127.0.0.1:8000 ...
echo Tekan CTRL+C untuk menghentikan server.
echo.
"%PHP_BIN%" artisan serve --host=127.0.0.1 --port=8000
pause
