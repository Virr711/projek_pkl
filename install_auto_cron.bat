@echo off
title ALISA - Install Automatic Background Task Scheduler
color 0A
echo =======================================================================
echo   🚀 PENJADWALAN OTOMATIS WHATSAPP BACKGROUND (WINDOWS TASK SCHEDULER)
echo =======================================================================
echo.
echo Script ini akan mendaftarkan tugas otomatis di Windows agar setiap hari
echo jam 08:00 WIB, sistem akan MENGIRIM NOTIFIKASI WHATSAPP H-30 / H-7 OTOMATIS
echo langsung ke HP penerima TANPA PERLU MEMBUKA WEBSITE!
echo.
echo =======================================================================

schtasks /create /tn "ALISA_BPJ_Auto_WA_Cron" /tr "C:\xampp\php\php.exe %~dp0cron_whatsapp.php" /sc daily /st 08:00 /f

echo.
echo =======================================================================
echo   ✅ PENJADWALAN OTOMATIS BERHASIL DIPASANG!
echo   Setiap hari jam 08:00 WIB, notifikasi WA akan terkirim otomatis di background.
echo =======================================================================
pause
