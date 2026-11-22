# Radnóti Merch

A Szegedi Radnóti Miklós Kísérleti Gimnázium fekete és fehér kapucnis pulóvereinek bemutató- és rendelési oldala.

- Éles oldal: https://rmgmerch-23073.web.app
- Szervezői felület: https://rmgmerch-23073.web.app/admin
- Firebase-projekt: `rmgmerch-23073` (Hosting + Firestore + Authentication / Google)

## Felépítés

- `app/` – a közös felület forrásai: `page.tsx` (termékoldal, kosár, belépés, Fiók menü), `admin/view.tsx` (kezelőfelület), CSS-ek (sorrend: `merch.css` → `redesign.css` → `typography.css`).
- `firebase/` – React + Vite frontend, Firestore-szabályok, Hosting-beállítás. A `prepare.mjs` az `app/` fájlokból generálja a `firebase/src/storefront.tsx`, `admin.tsx` és CSS-fájlokat (ezek nincsenek a tárolóban).
- `public/` – képek, Manrope betűkészlet, nyilvános Firebase-klienskonfiguráció (nem titok).

## Publikálás

A `main` ágra feltöltött minden módosítás után a GitHub Actions (`.github/workflows/publikalas.yml`) lefuttatja a buildet és a típusellenőrzést, majd publikál a Firebase Hostingra; ha a `firebase/firestore.rules` változott, a szabályokat is.

A Firestore-szabályokat a GitHub nem tudja publikálni (nincs rá jogosultsága); ezt a `Szabalyok feltoltese.cmd` végzi Windows-on, bejelentkezett Firebase CLI-vel.

Helyi fejlesztés a `firebase/` mappából: `npm ci`, `npm run dev -- --port 5174`, `npm run build`, `npm run check`.
