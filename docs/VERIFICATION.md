# Zapis o preverjanju

Preverjeno 14. septembra 2026, PHP 8.4 in MySQL 8.0.

## Testi

`php tests/run.php` — **20 testov, vsi uspešni.** Zaganjalnik zavrne vsako bazo, katere ime se ne konča na `_test`.

## Preverjanje datotek schema.sql in seed.sql

Obe datoteki sta bili uvoženi v popolnoma prazno bazo:

- 13 izdelkov, 185 landingov, 18 mnenj, 12 slik, 1 skrbnik.
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
| Spreminjajoča zahteva brez žetona CSRF | 419 |
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

## Napake, odkrite in odpravljene med preverjanjem

- **Seznam ponudnikov AI je ostal prazen po prijavi.** Nalagal se je samo ob zagonu strani, ko uporabnik še ni bil prijavljen. Zdaj se naloži tudi po uspešni prijavi.
- **Vgrajeni strežnik PHP je vračal HTML namesto CSS in JS.** Vstopna točka je prestrezala tudi `/assets/*`. Dodan je `bin/router.php`, ki obstoječe datoteke prepusti strežniku. Apache tega ne potrebuje, ker to opravi `public/.htaccess`.
- **Seja se je poskušala zagnati v ukazni vrstici** in je pri polnjenju podatkov ter testih sprožala opozorila. `Session` je zdaj v CLI neaktiven.
