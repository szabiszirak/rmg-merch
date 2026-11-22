@echo off
chcp 65001 >nul
cd /d "%~dp0firebase"
echo RMG Merch: Firestore-szabalyok feltoltese... (a weboldalt a GitHub publikalja)
echo.
(
  call npx --yes firebase-tools@14.26.0 deploy --project rmgmerch-23073 --only firestore:rules --non-interactive
) > deploy-log.txt 2>&1
if errorlevel 1 (
  type deploy-log.txt
  echo.
  echo HIBA: a feltoltes nem sikerult. Szolj Claude-nak.
) else (
  echo KESZ: a szabalyok fent vannak.
)
echo.
pause
