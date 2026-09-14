@echo off
title ALISA - Test Cron WA Background In 1 Minute
color 0E
echo =======================================================================
echo   ⏳ TIMER DETIK TERBALIK: NOTIFIKASI WA AKAN TERKIRIM DALAM 1 MENIT...
echo =======================================================================
echo.
echo Menunggu 60 detik (1 menit)... Anda boleh menutup atau meminimalkan window ini.
echo.
timeout /t 60 /nobreak
echo.
echo =======================================================================
echo   🚀 1 MENIT BERLALU! MENGIRIM WHATSAPP OTOMATIS SEKARANG...
echo =======================================================================
C:\xampp\php\php.exe "%~dp0cron_whatsapp.php"
echo.
echo =======================================================================
echo   ✅ NOTIFIKASI WA BERHASIL TERKIRIM KE NOMOR WHATSAPP TARGET!
echo =======================================================================
pause
