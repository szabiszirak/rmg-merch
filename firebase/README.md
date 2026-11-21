# Firebase-változat

Az aktuális dizájnt és a pulóverfotókat használja. A rendeléseket Cloud Firestore tárolja, a szervező Google-fiókkal lép be. A vásárlók is Google-fiókkal lépnek be (jobb felül „Belépés”, belépve „Fiók” menü a saját rendelésekkel); rendelni csak belépve lehet. A készpénzes fizetést az admin jelöli.

## Első telepítés

1. Firebase-projekt létrehozása a saját Google-fiókkal. A projektet nem kell fizetős csomagra állítani ehhez a Hosting + Firestore + Authentication megoldáshoz; a szolgáltatások kvótái érvényesek.
2. Webalkalmazás regisztrálása a projektben. Az SDK-beállításokat a `../public/firebase-config.json` fájlba kell tenni. Az objektumhoz egy `adminEmail` mező is szükséges az engedélyezett szervező Google-címével. Ezek nyilvános kliensbeállítások; a jogosultságot a Firestore-szabályok biztosítják.
3. Authentication: a Google szolgáltató engedélyezése. A Hosting-domain legyen az engedélyezett domainek között.
4. Cloud Firestore: adatbázis létrehozása, javasolt régió: eur3 (EU). Éles szabályokkal induljon, ne nyilvános tesztmóddal.
5. A `firestore.rules` fájl `admin()` függvényében az engedélyezett, ellenőrzött Google-cím szerepeljen. Engedélyezett szervező: sziraksz@gmail.com.
6. `npm install`, majd `npm run build`.
7. `firebase login`, majd `firebase deploy --project PROJEKT_AZONOSITO --only hosting,firestore:rules,firestore:indexes`.

Az első belépés után a `/admin` oldalon kell beállítani a rendelési időszakot. Dátum nélkül nincs rendelésleadás. A határidőt, az árat, a darabszámokat és az adminműveletek jogosultságát a Firestore-szabályok is ellenőrzik.

Az automatikus záró e-mail küldése ebben a változatban sincs bekötve; a három munkalapos Excel-kompatibilis export elérhető. A Sites adatbázis nem kerül automatikusan átmásolásra.

## Konfiguráció alakja

```json
{"apiKey":"Firebase web API key","authDomain":"PROJECT.firebaseapp.com","projectId":"PROJECT","appId":"Firebase web app id","adminEmail":"szervezo@example.com"}
```

Ne tegyél szolgáltatásfiók-kulcsot vagy Google-jelszót a klienskonfigurációba.

