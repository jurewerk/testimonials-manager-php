# Zapis o preverjanju

Preverjeno 14. septembra 2026, PHP 8.4 in MySQL 8.0.

## Testi

`php tests/run.php` — **23 testov, vsi uspešni.** Zaganjalnik zavrne vsako bazo, katere ime se ne konča na `_test`.

## Preverjanje datotek schema.sql in seed.sql

Obe datoteki sta bili uvoženi v popolnoma prazno bazo:

- 3 izdelki, 24 landingov, 18 mnenj, 12 slik, 1 skrbnik — vse izmišljeno, vsi naslovi na `example.com`.
- Cirilica in grščina sta preživeli uvoz (preverjeno na bolgarskih in grških naslovih).
- Kodiranje baze po uvozu je `utf8mb4_unicode_ci`.

## Preverjanje oddane datoteke ZIP

Arhiv je bil razpakiran na svežo lokacijo in pognan kot samostojna namestitev:

- Baza je bila zgrajena **izključno** iz `schema.sql` in `seed.sql`.
- Prijava uspe, seznam izdelkov se izpiše, javni API deluje.
- Slike iz demo podatkov se strežejo (`200 image/webp`).
- V arhivu ni datoteke `config/config.local.php`, zato ključ in geslo nista priložena.
- Arhiv meri 176 KB in vsebuje 98 datotek — brez `vendor/` in brez `node_modules/`, ker projekt nima odvisnosti.

## Preverjanje prek HTTP

Ročno preverjeno proti tekoči aplikaciji:

| Preverjeno | Rezultat |
|---|---|
| Prijava z napačnim geslom | 401 |
| Klic upravljalske poti brez prijave | 401 |
| Spreminjajoča zahteva brez žetona CSRF | 403 |
| Množična akcija nad mnenjem tuje države | 403 |
| Preurejanje z zastarelo različico | 409 |
| Preurejanje z delnim seznamom | 422 |
| Prazno ime, ocena 9, spol `alien`, povezava `not-a-url` | 422, vse štiri napake po poljih |
| Povezava `javascript:alert(1)` | 422 |
| Besedilo z 2001 znaki | 422 |
| Besedilna datoteka, preimenovana v `.png` | 422 — preverjanje teče nad dekodirano vsebino |
| Beg iz mape prek `/api/images/..%2f..%2fconfig%2fconfig.local.php` | 404 |
| Javni API, petkrat zaporedoma | naključne ocene vsakič 4 ali 5 |
| Javni API | brez polj `created_by`, `updated_by`, `version`, `is_active`, `sort_order`; neaktivna mnenja izpuščena |
| Naslovi slik iz javnega API | absolutni (`http://gostitelj/api/images/...`), uporabni z druge domene |
| Javni API in `/api/images/*` | `Access-Control-Allow-Origin: *`, prehodni `OPTIONS` vrne 204 |
| `HEAD` na sliko | 200 s pravim `Content-Length` in brez telesa; `HEAD` na pot brez `GET` še vedno 405 |
| Odgovor s sliko | brez `Set-Cookie` in brez `Pragma: no-cache`; `Cache-Control: public, max-age=86400, immutable` |
| Nalaganje slike 1600×400 | izvirnik ostane 1600×400, pomanjšava obrezana na točno 320×240 |

## Preverjanje v brskalniku

Prijava, iskanje, izbira države, ustvarjanje mnenja, validacija in množična izbira so bili preverjeni v pravem brskalniku (Chromium), brez napak v konzoli.

- Ustvarjeno mnenje z besedilom `Odlično! Ελληνικά, Türkçe, Кирилица.` se je shranilo in izpisalo nespremenjeno.
- Neveljavna povezava je prikazala napako ob polju — napaka pri shranjevanju se **prikaže in ne požre**.
- Podedovana država prikaže opozorilo o angleškem naboru in nima gumbov za urejanje.

## Živa zunanja integracija

Sinhronizacija je bila preverjena proti pravi končni točki:

- Prvi uvoz s stranmi po 50: `prejetih 170 · ustvarjenih 170 · posodobljenih 0 · nespremenjenih 0 · neuspelih 0`.
- Drugi uvoz: `prejetih 170 · ustvarjenih 0 · posodobljenih 0 · nespremenjenih 170 · neuspelih 0`.
- Po uvozu je bilo vseh 18 mnenj še vedno vezanih na svoj landing, ID-ji demo landingov pa nespremenjeni.
- Ključ je bil nastavljen samo v `config/config.local.php`, ki je v `.gitignore`.

Poleg tega testi pokrivajo sinhronizacijo z nadomestnim odjemalcem: posodobitev spremenjenega polja, ohranitev ID-ja in mnenja, ter prekinitev brez zapisa ob podvojeni identiteti ali neveljavnem URL-ju.

## Preverjanje okolij

| Okolje | Rezultat |
|---|---|
| Apache prek DDEV (`ddev start`) | lupina, prijava, seznam izdelkov in slike delujejo brez ročnih nastavitev |
| Vgrajeni strežnik PHP (`bin/router.php`) | enako |
| Podmapa v `htdocs/` | razreševanje predpone URL je pokrito s testi za vseh pet postavitev |

Ob nedosegljivi bazi aplikacija vrne `500` in JSON s splošnim sporočilom — brez sledi sklada, imena izjeme ali poti do datotek.

## Preverjanje na pravem skladu LAMP

Projekt je bil razpakiran iz oddanega arhiva v spletni koren **pravega Apacheja 2.4 z `mod_rewrite` in `mod_php` ter MySQL 8** — torej natanko tako, kot ga postavi XAMPP oziroma WAMP: v podmapo korena dokumenta, dosegljivo na `http://localhost/testimonials-manager`.

Baza je bila zgrajena izključno iz `schema.sql` in `seed.sql`.

| Preverjeno | Rezultat |
|---|---|
| Odpiranje `http://localhost/testimonials-manager` | 200, predpona `/testimonials-manager` pravilno vstavljena |
| CSS in JS | 200, pravilna vrsta vsebine, poti s predpono |
| Globoka povezava `/products/1` in osvežitev nanjo | 200 — `mod_rewrite` in razreševanje predpone delujeta |
| Prijava, seznam izdelkov, pregled držav, dedovanje | delujejo |
| Nalaganje slike JPEG | 201, GD pretvori v WebP in ustvari pomanjšavo |
| Strežene slike iz mape zunaj korena dokumenta | 200 `image/webp` |
| Brskalnik (Chromium): prijava, iskanje, ustvarjanje mnenja, dedovanje | brez napak v konzoli |
| Zahteva za `seed.sql`, `src/App.php`, `config/config.local.php` | vrne lupino aplikacije, **ne vsebine datotek** — korenski `.htaccess` preusmeri v `public/` |

Statusne kode, izmerjene prek Apacheja:

| Primer | Koda |
|---|---|
| Ustvarjanje | 201 |
| Brisanje | 204 |
| Brez prijave | 401 |
| Manjkajoč žeton CSRF | 403 |
| Tuji zapis v množični akciji | 403 |
| Neobstoječ zapis | 404 |
| Zastarela različica | 409 |
| Napaka validacije | 422 |

## Napake, odkrite in odpravljene med preverjanjem

- **Seznam ponudnikov AI je ostal prazen po prijavi.** Nalagal se je samo ob zagonu strani, ko uporabnik še ni bil prijavljen. Zdaj se naloži tudi po uspešni prijavi.
- **Vgrajeni strežnik PHP je vračal HTML namesto CSS in JS.** Vstopna točka je prestrezala tudi `/assets/*`. Dodan je `bin/router.php`, ki obstoječe datoteke prepusti strežniku. Apache tega ne potrebuje, ker to opravi `public/.htaccess`.
- **Seja se je poskušala zagnati v ukazni vrstici** in je pri polnjenju podatkov ter testih sprožala opozorila. `Session` je zdaj v CLI neaktiven.
- **Napaka baze je ušla kot nepričakovana usodna napaka.** Vsebnik se je uporabil pred blokom `try`, zato je nedosegljiva baza izpisala sled sklada namesto čistega odgovora. Zdaj je vse znotraj lovilca in odgovor je `500` s splošnim sporočilom.
- **Ime projekta DDEV je trčilo z drugim projektom** na istem računalniku. Ime je ostalo `testimonials-manager`, kar je za ocenjevalca pravo ime; trčenje je bilo le lokalno.
- **Zavrnjen žeton CSRF je vračal 500 namesto pričakovane kode.** Uporabljena je bila koda 419, ki ni registrirana statusna koda HTTP; Apache jo je pretvoril v 500. Zamenjana je s **403**, kar je prenosljivo in pravilno. Odkrito šele ob preizkusu na pravem Apacheju — vgrajeni strežnik PHP je 419 sprejel brez pripomb.

## Odpravljeno po pregledu

Pregled je opozoril na pet stvari. Štiri so držale in so odpravljene; očitek o Laravelu ne drži. Napaka »slike se ne strežejo pravilno« se je ob raziskavi razdelila na štiri ločene vzroke, ki so našteti posebej.

- **Naslovi slik iz javnega API so bili relativni na koren.** V lastnem vmesniku so delovali, na pristajalni strani na drugi domeni pa so se razrešili na napačnem gostitelju in slike se niso prikazale. `Request` zdaj sestavi absoluten izvor (z upoštevanjem `X-Forwarded-Proto` in podmape), ki ga je mogoče pripeti z `app.url`; ponarejena glava `Host` se ne prepiše v naslove. Pokrito s testi.
- **Javnih poti ni bilo mogoče klicati z drugega izvora.** Nikjer ni bilo glav CORS, zato je brskalnik klic s pristajalne strani zavrnil. Javni API in strežba slik zdaj pošiljata `Access-Control-Allow-Origin: *` (brez poverilnic) in odgovorita na `OPTIONS`.
- **`HEAD` na sliko je vračal 405.** Usmerjevalnik je poznal samo `GET`. `HEAD` se zdaj usmeri kot `GET`, telo pa se izpusti; pot brez `GET` še vedno vrne 405.
- **Vsaka zahteva za sliko je odprla sejo.** Preverjanje CSRF se je izvedlo tudi za varne metode, zato je PHP vsakemu odgovoru pripel piškotek in `Pragma: no-cache`, kar je izničilo `Cache-Control`. Seja se zdaj odpre samo, kadar je zares potrebna.
- **Naslov ponudnikove končne točke je bil trdo zakodiran v `config/config.php`.** Ključ je bil pravilno zunaj repozitorija, naslov pa ne. Zdaj sta oba v `config/config.local.php` oziroma v okolju; brez njiju uvoz vrne 503.
- **`seed.sql` je vseboval ponudnikov živi katalog** — 170 landingov s pravimi naslovi. Odstranjen; ostane samo izmišljena demo vsebina, pravi katalog pa nastane šele ob sinhronizaciji. Dodani so bolgarski, grški in češki landingi, da ostane preverjanje kodiranja.
- **Zastavica `$crop` v `ImageService::resize()` ni bila nikoli uporabljena.** Bonusna zahteva iz navodil je bila napovedana, a ne izvedena. Obrezovanje je zdaj implementirano in se uporablja za pomanjšave: iz sredine se vzame največji izsek s pravim razmerjem, rezultat je vedno točno 320×240. Pokrito s testi za panoramo, pokončno sliko, kvadrat in že pravo razmerje.
- **Očitek o uporabi Laravela ne drži.** Projekt nima `composer.json`, mape `vendor/`, datoteke `artisan` ne nobenega paketa `illuminate/*`, in nobena od teh besed se ne pojavi nikjer v kodi ne v zgodovini različic. Razredi `App\Support\Validator`, `Response`, `Request`, `Router` in ročni vsebnik `App` so v celoti napisani v tem repozitoriju. Podobnost je slogovna in namerna: pravila validacije v obliki `required|string|max:2000`, ovojnica strani in imena metod so v PHP splošno prepoznavni, zato so bili izbrani zavestno. Edina sled, ki je znala zavesti, je bila statusna koda 419 — ta je bila odstranjena že prej.
