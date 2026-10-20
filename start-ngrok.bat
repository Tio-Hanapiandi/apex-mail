@echo off
title ApexMail - Ngrok Webhook Tunnel
echo ========================================================
echo   ApexMail Collaboration Suite - Ngrok Tunnel (Port 8000)
echo ========================================================
echo.
echo Pastikan server Laravel sudah berjalan di port 8000 (start-server.bat)!
echo.
echo Menjalankan tunnel publik ngrok untuk port 8000...
echo Tekan CTRL+C untuk menutup tunnel.
echo.
ngrok http 8000
pause
