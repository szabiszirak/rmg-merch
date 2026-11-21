@echo off
chcp 65001 >nul
cd /d "%~dp0firebase"
echo Radnoti Merch publikalasa... (kb. 1 perc, ne zard be az ablakot)
echo.
(
  call npm run build && call npm run check && call npx --yes firebase-tools@14.26.0 deploy --project rmgmerch-23073 --only "hosting,firestore:rules" --non-interactive
) > deploy-log.txt 2>&1
if errorlevel 1 (
  type deploy-log.txt
  echo.
  echo HIBA: a publikalas nem sikerult. Szolj Claude-nak.
) else (
  echo KESZ: az oldal frissult: https://rmgmerch-23073.web.app
)
echo.
pause
