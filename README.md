# Testimonial Collector

WordPress plugin szöveges és videós vélemények (testimonialok) gyűjtésére — böngészős videófelvétellel, jóváhagyási folyamattal és lapozható megjelenítéssel. A [testimonial.to](https://testimonial.to) mintájára, saját WordPress oldalon.

## Funkciók

- **Kétféle vélemény**: írásos és videós (a videót a látogató közvetlenül a böngészőben rögzíti a kamerájával, vagy fájlként tölti fel)
- **Jóváhagyási folyamat**: minden beküldés "Függőben" státuszba kerül; az admin felületen egy kattintással jóváhagyható, elutasítható vagy visszavonható. A menüben piros jelvény mutatja a függőben lévők számát.
- **Két shortcode**:
  - `[testimonial_form]` — beküldő űrlap
  - `[testimonial_wall]` — jóváhagyott vélemények lapozható fala (`per_page` attribútummal felülírható a lapméret: `[testimonial_wall per_page="4"]`)
- **Élő előnézet** a beállítások oldalon: az űrlap és a köszönő oldal azonnal mutatja a módosításokat
- **Testreszabható**: logó, színek (elsődleges, csillag, háttér), világos/sötét téma, minden szöveg magyarul és angolul
- **Vezérlő kérdések** (Questions) a beküldőnek
- **Gyűjtés típusa**: szöveg + videó / csak szöveg / csak videó
- **5 csillagos értékelés** (kikapcsolható)
- **Extra mezők**: cég/szerep, közösségi link, fotó, vélemény címe — mind kapcsolható
- **Köszönő oldal**: kép + cím + üzenet, testreszabható
- **E-mail megerősítés** (opcionális dupla megerősítés a beküldő e-mail címére)
- **Consent (hozzájárulás)**: kötelező / opcionális / rejtett
- **Karakterlimit** a szöveges véleményekhez (0 = nincs limit)
- **Videó limitek**: max. hossz (mp) és fájlméret (MB)
- **iOS felvétel tiltás**: iPhone/iPad esetén fájlfeltöltés felajánlása felvétel helyett
- **Értesítő e-mail** az adminnak minden új beküldésről
- **GitHub-alapú frissítés**: a plugin a repó release-eiből frissíthető a WordPress admin Bővítmények oldaláról

## Telepítés

1. Töltsd le a `testimonial-collector.zip` fájlt (vagy a repó legfrissebb release-ét).
2. WordPress admin → Bővítmények → Új hozzáadása → Bővítmény feltöltése → zip kiválasztása → Telepítés → Aktiválás.
3. A bal oldali menüben megjelenik a **Testimonials** menüpont.

## Használat

1. **Beállítások**: Testimonials → Settings — nyelv, logó, színek, szövegek, kérdések, köszönő oldal. Jobb oldalon élő előnézet.
2. **Beküldő oldal**: hozz létre egy oldalt, illeszd be: `[testimonial_form]`
3. **Vélemény fal**: az oldalra, ahol a jóváhagyott vélemények jelenjenek meg: `[testimonial_wall]`
4. **Jóváhagyás**: Testimonials → All Testimonials — a függőben lévőknél *Approve* / *Reject* gomb. A videó közvetlenül a listában és a szerkesztő oldalsávjában is lejátszható.

## Frissítés GitHubról

A plugin a `TC_Updater` osztályon keresztül figyeli a GitHub repó release-eit. Új verzió kiadása:

1. Emeld a verziószámot a `testimonial-collector.php` fejlécében és a `TC_VERSION` konstansban.
2. Commit + push.
3. Készíts release-t `vX.Y.Z` taggel (ajánlott: csatolj egy `testimonial-collector.zip` asset-et, amiben a plugin mappa van).
4. A WordPress admin Bővítmények oldalán megjelenik a frissítés (a plugin 6 óránként ellenőriz; a Vezérlőpult → Frissítések oldalon azonnal is ellenőrizhető).

## Fejlesztői jegyzetek

- Custom post type: `tc_testimonial` (nem publikus, csak adminban látszik)
- Státuszok: `pending` = jóváhagyásra vár, `publish` = jóváhagyva, `draft` = e-mail megerősítésre vár, kuka = elutasítva
- A videók és fotók a WordPress médiatárba kerülnek, a testimonialhoz csatolva
- Biztonság: nonce ellenőrzés, honeypot spam-védelem, fájltípus- és méretellenőrzés, minden kimenet escape-elve
- A `webm` feltöltést a plugin engedélyezi (`upload_mimes` szűrő)

## Ismert korlátok

- A videós vízjelezés nincs beépítve (szerveroldali videófeldolgozást igényelne)
- A böngészős felvétel Safari alatt mp4-et, más böngészőkben webm-et rögzít — mindkettőt kezeli a szerver
