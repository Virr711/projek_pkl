@echo off
title ALISA - Local Unlimited WhatsApp Gateway Server (Port 3000)
color 0A

echo =======================================================================
echo   ALISA - LOCAL UNLIMITED WHATSAPP GATEWAY SERVER (BPJ TEGAL)
echo =======================================================================
echo.

set "NODE_CMD=node"

where node >nul 2>nul
if %errorlevel%==0 goto NODE_FOUND

if exist "C:\Program Files\nodejs\node.exe" (
    set "NODE_CMD=C:\Program Files\nodejs\node.exe"
    set "PATH=%PATH%;C:\Program Files\nodejs\"
    goto NODE_FOUND
)

if exist "%LocalAppData%\Programs\node\node.exe" (
    set "NODE_CMD=%LocalAppData%\Programs\node\node.exe"
    set "PATH=%PATH%;%LocalAppData%\Programs\node\"
    goto NODE_FOUND
)

if exist "C:\Program Files (x86)\nodejs\node.exe" (
    set "NODE_CMD=C:\Program Files (x86)\nodejs\node.exe"
    set "PATH=%PATH%;C:\Program Files (x86)\nodejs\"
    goto NODE_FOUND
)

color 0C
echo ERROR: Node.js belum terdeteksi di laptop ini.
echo.
echo CARA MEMPERBAIKI:
echo 1. Jika Anda BARU SAJA menginstall Node.js, silakan RESTART / REBOOT laptop Anda.
echo    Atau tutup semua jendela CMD/Explorer lalu buka kembali start_wa_gateway.bat
echo 2. Jika belum download Node.js, download versi LTS dari https://nodejs.org/
echo.
echo =======================================================================
pause
exit /b

:NODE_FOUND
echo STATUS: Node.js terdeteksi. Menjalankan WhatsApp Gateway Server.
echo.

cd /d "%~dp0wa_gateway"

if not exist "node_modules\" (
    echo Installing dependencies (npm install)
    call npm install
    echo.
)

echo =======================================================================
echo   Server berjalan pada: http://localhost:3000
echo   Scan QR Code saat pertama kali terhubung dengan WhatsApp Anda.
echo   Buka http://localhost/bengkel_bpj/notifikasi_wa.php
echo.
echo   [PERHATIAN PENTING]:
echo   Jangan meng-klik mouse di dalam jendela hitam CMD ini!
echo   Jika judul jendela bertuliskan 'Select...', tekan tombol ENTER atau ESC
echo   pada keyboard agar server tidak ter-pause (freeze) oleh Windows.
echo =======================================================================
echo.

"%NODE_CMD%" server.js

pause
