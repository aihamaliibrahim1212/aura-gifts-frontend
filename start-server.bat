@echo off
echo Starting Aura Gifts server on http://localhost:8000 ...
echo Press Ctrl+C to stop.
echo.
cd /d "c:\Users\Aiham\Desktop\aura-gifts\backend-php"
"C:\Program Files\PHP\8.5.7\nts\x64\php.exe" artisan serve --host=127.0.0.1 --port=8000
pause
