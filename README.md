# Testimonials Manager

Samostojna administracija za upravljanje mnenj kupcev (testimonialov) na pristajalnih straneh — po izdelku in po državi.

**PHP 8.1+ brez ogrodja, MySQL prek PDO s pripravljenimi stavki, frontend v čistem JavaScriptu, HTML in CSS.** Brez Composerja, brez npm, brez knjižnic. Vmesnik aplikacije je v angleščini, kot zahteva naloga; ta dokumentacija je v slovenščini.

---

## Hitri zagon na XAMPP / WAMP / LAMP

Potrebni so Apache z `mod_rewrite`, PHP 8.1+ z razširitvama **GD** in **PDO MySQL**, ter MySQL 8 oziroma MariaDB 10.4+.

**1. Postavite projekt** v spletni koren, npr. `C:\xampp\htdocs\testimonials-manager` ali `/var/www/html/testimonials-manager`.

**2. Ustvarite bazo in uvozite podatke:**

```sh
mysql -u root -p -e "CREATE DATABASE testimonials CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p testimonials < schema.sql
mysql -u root -p testimonials < seed.sql
```

Obe datoteki lahko uvozite tudi prek phpMyAdmina (zavihek Uvoz).

**3. Nastavite dostop do baze.** Kopirajte `config/config.local.php.example` v `config/config.local.php` in vpišite svoje podatke:

```php
return [
    'db' => ['host' => '127.0.0.1', 'port' => 3306, 'name' => 'testimonials', 'user' => 'root', 'pass' => ''],
    'landings_api' => ['key' => 'kljuc-iz-naloge'],
];
```

Ta datoteka je v `.gitignore`, zato ključ in geslo nikoli ne gresta v repozitorij.

**4. Odprite aplikacijo** in se prijavite z **admin@example.test** / **local-demo-only**.

| Postavitev | Naslov |
|---|---|
| Projekt v `htdocs/` | `http://localhost/testimonials-manager` |
| Navidezni gostitelj na `public/` (najčistejše) | `http://testimonials.localhost` |

Korenski `.htaccess` preusmeri zahteve v `public/`, zato prva možnost deluje brez nastavljanja. Predpono naslova razreši `App\Support\Request`, kar je pokrito s testi, tako da enaka koda teče v korenu in v podmapi.

### Brez Apacheja

Za hiter preizkus zadošča vgrajeni strežnik PHP:

```sh
php -S 127.0.0.1:8000 -t public bin/router.php
```

`bin/router.php` samo pove vgrajenemu strežniku, naj obstoječe datoteke postreže sam, vse ostalo pa preda `public/index.php`. V produkciji ni v uporabi.

---

## Zagon z DDEV

```sh
ddev start
```

Konfiguracija je priložena; ob zagonu se uvozita `schema.sql` in `seed.sql`, aplikacija pa teče na `https://testimonials-manager.ddev.site`.

---

## Sinhronizacija landingov

Landingov ne vnašamo ročno — uvozijo se iz ponudnikove končne točke. Ključ vpišite v `config/config.local.php`, nato:

```sh
php bin/sync.php                                   # vse
php bin/sync.php --sku=drivewaypro,abforge         # samo izbrani SKU-ji
php bin/sync.php --country=IT --limit=50           # ena država, strani po 50
```

V vmesniku isto opravi gumb **Sync landing pages** na seznamu izdelkov. Uvoz teče sinhrono, zato **delavec za vrste ni potreben** — 170 landingov se uvozi v manj kot sekundi.

### Kako je zagotovljeno, da uvoz ne pobriše mnenj

To je najbolj občutljiv del naloge, zato je rešen v dveh fazah:

1. **Prenos in preverjanje.** Vse strani se prenesejo in v celoti preverijo, preden se odpre transakcija. Manjkajoče polje, neveljaven URL, podvojena identiteta ali dvojni par izdelek/država prekinejo uvoz, **ne da bi se karkoli zapisalo**.
2. **Upsert v eni transakciji.** Zapisi se posodabljajo po ponudnikovem stabilnem `external_id`. Tabele se **nikoli** ne praznijo — nobenega `TRUNCATE` ali `DELETE`. Lokalni `landings.id` zato ostane isti in mnenja ne izgubijo reference.

Ključ potuje izključno v glavi `X-Api-Key`. Ob napaki se vrne varno sporočilo; ponudnikovo telo odgovora se nikoli ne izpiše, ker bi lahko vsebovalo ključ.

Preverjeno proti pravi končni točki: prvi uvoz `170 ustvarjenih`, drugi `170 nespremenjenih`, ID-ji in mnenja nedotaknjeni.

---

## Kaj aplikacija zna

### Obvezni del

- **Iskalnik izdelkov** po krovnem SKU-ju, naslovu in opisu, s **straničenjem in sortiranjem na strežniku**. V vsaki vrstici sta število lokaliziranih landingov in skupno število mnenj. Stanje iskanja ostaja v naslovu URL.
- **Pregled držav** izbranega izdelka s števci ob vsaki državi, tako da uredniku ni treba klikati po vseh. Države **niso trdo zakodirane** — izhajajo iz uvoženih landingov.
- **Urejanje mnenj**: ime avtorja, besedilo, povezava, ocena, spol, stikalo aktivno/neaktivno, vrstni red in poljubno število slik. Brisanje zahteva potrditev. Shranjevanje je izrecno, z gumbom; stanje »Unsaved changes« je ves čas vidno, napaka pri shranjevanju pa se **prikaže in ne požre**.
- **Ocena**: fiksna 1–5 ali `random`. Pri `random` se v bazo shrani `NULL`, ocena pa se določi ob vsakem prikazu med 4 in 5, da povprečje ostane realno.
- **Slike**: nalaganje več datotek hkrati, brisanje in vrstni red. Dovoljeni so JPG, PNG in WebP do 5 MB, kar se preverja **na strežniku** nad dekodirano vsebino, ne prek atributa `accept` ali končnice. Datoteke se shranijo **zunaj korena dokumenta** z generiranim imenom UUID; izvirno ime je zgolj metapodatek. Ustvari se tudi pomanjšava.
- **Prijava** z geslom, zgoščenim s `password_hash()`, sejo in zaščito vseh strani ter klicev API.

### Bonus del

- **Kopiranje med državami** s štirimi strategijami: dodaj, zamenjaj, samo kadar je prazno, preskoči dvojnike. Kopije so samostojni zapisi z **lastnimi datotekami slik**, zato urejanje kopije ne vpliva na izvirnik.
- **Množične akcije**: označi več mnenj → aktiviraj, deaktiviraj, izbriši, vse v eni transakciji.
- **Drag & drop** za vrstni red mnenj in slik; vsak premik shrani celoten seznam ID-jev v enem klicu.
- **Obdelava slik**: zmanjšanje na največ 2000 px, pretvorba v WebP, pomanjšava 320×240. Ponovno kodiranje odstrani metapodatke.
- **Dnevnik sprememb** z vrednostmi pred in po, avtorjem in časom, dostopen pri vsakem zapisu.
- **AI: prevajanje in generiranje imen** prek izbranega ponudnika, z obveznim predogledom pred shranjevanjem.

---

## AI funkcije

Pravi ponudnik **ni priklopljen in nič ne stane**. Vsak ponudnik je svoja implementacija za skupnim vmesnikom `App\Ai\AiProvider`:

```
src/Ai/
├── AiProvider.php          vmesnik: translate() in generateAuthorName()
├── MockProvider.php        skupno vedenje lažnih implementacij
├── OpenAiProvider.php      ┐
├── ClaudeProvider.php      ├─ izbira v vmesniku
├── GeminiProvider.php      ┘
└── AiProviderFactory.php   registracija in izbira po ključu
```

Implementacije vrnejo besedilo s kodo države v oglatih oklepajih:

```
prevod v SI: [SI] This is my translated text
ime za SI:   [SI] Janez Novak
ime za IT:   [IT] Mario Rossi
```

Prava storitev bi se pozneje priklopila tako, da bi implementirala isti vmesnik in se registrirala v tovarni — **brez dotikanja ostale kode**.

---

## Podatkovni model

```
products ──┬── landings ──┬── testimonials ──── testimonial_images
           │              │
    krovni SKU     external_id je      vrstni red,
                   stabilen ključ      revizijska polja
                   sinhronizacije
```

- `landings.external_id` je edinstven in je **edini ključ sinhronizacije**; par `(product_id, country_code)` je dodatno enolično omejen.
- Izdelek hrani samo SKU. Naslov in opis se bereta z angleškega master landinga, zato se besedilo izdelka ne podvaja in ne more postati neskladno.
- `testimonials` ima omejitev `CHECK`, ki uveljavi pravilo ocen: `random` pomeni `rating IS NULL`, `fixed` pa vrednost med 1 in 5.
- Revizijska polja: `created_at`, `updated_at`, `created_by`, `updated_by`, poleg tega pa še ločena tabela `activity_logs`.
- Stolpec `version` na landingu in mnenju omogoča optimistično zaklepanje: zastarel zapis vrne **409**.
- Vse tabele so `utf8mb4_unicode_ci`; demo podatki vsebujejo cirilico, grščino in šumnike.

### Dedovanje angleškega nabora

Lokalizirana država podeduje angleška mnenja, dokler **nima nobenega svojega zapisa**, niti neaktivnega. Če ima svoje zapise in so vsi deaktivirani, prikaže prazen seznam — namerno, sicer ne bi bilo mogoče zavestno ne prikazati ničesar. Podedovana mnenja so v vmesniku samo za branje; gumb **Copy locally to edit** iz njih naredi samostojne lokalne zapise.

Fizičnega podvajanja ali združevanja ni.

---

## API

Vse poti vračajo JSON. Uporabljene so smiselne metode in statusne kode: **201** ob ustvarjanju, **204** ob brisanju in preurejanju, **401** brez prijave, **403** ob tujem zapisu, **409** ob zastareli različici, **419** ob manjkajočem žetonu CSRF, **422** ob napaki validacije.

```text
POST   /api/login
POST   /api/logout
GET    /api/user

GET    /api/products?search=&sort=&direction=&per_page=&page=
GET    /api/products/{id}
GET    /api/products/{id}/landings

POST   /api/landings/sync

GET    /api/landings/{id}/testimonials
POST   /api/landings/{id}/testimonials
POST   /api/landings/{id}/testimonials/reorder
POST   /api/landings/{id}/testimonials/bulk-action
POST   /api/landings/{id}/copy-testimonials
POST   /api/landings/{id}/translate-testimonials

GET    /api/testimonials/{id}
PATCH  /api/testimonials/{id}
DELETE /api/testimonials/{id}
GET    /api/testimonials/{id}/activity
POST   /api/testimonials/{id}/generate-author-name
POST   /api/testimonials/{id}/images          (multipart: images[])
POST   /api/testimonials/{id}/images/reorder
DELETE /api/testimonial-images/{id}
GET    /api/images/{ime-datoteke}

GET    /api/ai-providers
GET    /api/public/landings/{external_id}/testimonials
```

Zadnja pot je namenoma javna in namenjena vgradnji v pristajalno stran: vrne samo **aktivna efektivna** mnenja, naključne ocene razreši v 4 ali 5 in izpusti notranja polja (avtorstvo, revizijo, različico, stanje aktivnosti).

---

## Varnost

- **Pripravljeni stavki povsod.** Nobena vrednost se ne lepi v SQL. Imena stolpcev za sortiranje se primerjajo s seznamom dovoljenih.
- **Gesla** so zgoščena s `password_hash()`; prijava ob neobstoječem računu vseeno izvede primerjavo, da se odzivni čas ne razlikuje.
- **CSRF**: vsaka spreminjajoča zahteva mora priložiti žeton iz seje v glavi `X-CSRF-Token`. Sejni piškotek je `HttpOnly` in `SameSite=Lax`, ob prijavi se ID seje zavrti.
- **Nalaganje datotek** se preverja nad dekodirano vsebino, datoteke pa se shranjujejo zunaj korena dokumenta in strežejo prek PHP. Poti se ne da izsiliti (`basename()` in poizvedba v bazi), zato beg iz mape ni mogoč.
- **Izpis** je v celoti ubežan; besedilo mnenj se nikoli ne interpretira kot HTML.
- **Napake** se zabeležijo v dnevnik strežnika, uporabnik pa dobi splošno sporočilo — brez sledi sklada in brez notranjih podrobnosti.
- Validacija vrne **samo deklarirana polja**, zato nepričakovan vnos ne more priti do baze.

---

## Testi

```sh
mysql -u root -p -e "CREATE DATABASE testimonials_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p testimonials_test < schema.sql
php tests/run.php
```

**20 testov.** Ker ogrodja niso dovoljena, je `tests/Harness.php` nekaj deset vrstic trditev in zaganjalnika. Pokrito je:

| Področje | Kaj se preverja |
|---|---|
| Validacija | obvezna polja, dolžine, dovoljene ocene in spoli, samo `http`/`https` povezave, neznana polja se zavržejo |
| Predpona URL | vseh pet podprtih postavitev strežnika |
| AI | vsi trije ponudniki, predpona `[XX]`, imena po državah, neznan ponudnik |
| Prijava | `password_hash()`, zavrnitev napačnega gesla |
| Dedovanje | podeduje le brez lastnih zapisov; neaktiven lokalni zapis dedovanje ustavi |
| Števci | pregled držav brez poizvedbe na državo |
| Vrstni red | zavrnitev delnega seznama, zastarele različice, zaporedni položaji od nič |
| Kopiranje | samostojnost kopij, strategija »samo kadar je prazno«, prevod prek ponudnika |
| Sinhronizacija | posodobitev spremenjenih polj, ohranjeni ID-ji in mnenja, prekinitev ob podvojeni identiteti ali neveljavnem URL-ju brez zapisa |
| Javni API | naključna ocena vedno 4 ali 5, brez notranjih polj |
| Kodiranje | cirilica, grščina, šumniki in emoji preživijo pot skozi bazo |

Zaganjalnik zavrne vsako bazo, katere ime se ne konča na `_test`.

---

## Struktura

```
public/            index.php (edina vstopna točka), .htaccess, assets/
src/
├── App.php        ročno povezan vsebnik storitev
├── Support/       Database (PDO), Request, Response, Router, Validator,
│                  Session, Csrf, Auth, izjeme
├── Repository/    poizvedbe, ena datoteka na tabelo
├── Service/       dedovanje, kopiranje, vrstni red, slike, sinhronizacija
├── Ai/            vmesnik ponudnika in lažne implementacije
├── Controller/    HTTP sloj, brez poslovne logike
└── View/          lupina HTML
bin/               seed.php, sync.php, create-admin.php, router.php
tests/             Harness.php in run.php
config/            config.php in zgled za lokalne nastavitve
storage/uploads/   naložene slike (zunaj korena dokumenta)
schema.sql seed.sql
```

Krmilniki ne vsebujejo poslovne logike — preverijo vnos in delo predajo storitvam. Storitve ne poznajo HTTP. Repozitoriji so edini, ki pišejo SQL.

### Zmogljivost

Naloga računa z okoli 300 izdelki × 20 držav × 50 mnenj. Zato:

- seznam izdelkov uporablja agregatne podpoizvedbe in en obhod baze, ne poizvedbe na vrstico;
- pregled držav prešteje mnenja z enim združenim `GROUP BY`, ne s poizvedbo na državo;
- slike se za celo stran mnenj naložijo z enim `IN (…)` — problema N+1 ni;
- indeksi pokrivajo `(landing_id, sort_order, id)`, `(landing_id, is_active)` in `(entity_type, entity_id, id)`;
- celoten vrstni red se zapiše z enim stavkom `CASE`.

---

## Zavestne odločitve

- **Sinhrona sinhronizacija.** Vrsta opravil bi na XAMPP zahtevala delavca, ki bi ga moral nekdo poganjati. Pri 170 landingih to ni potrebno.
- **Brez knjižnic.** Tudi razvojnih ni; testni zaganjalnik je zato lasten.
- **Preverjanje dvojnikov pri kopiranju** je natančno ujemanje imena in besedila, brez mehkega ujemanja.
- **Iskanje z `LIKE`** je pri nekaj sto izdelkih ustrezno; ločen iskalnik bi bil odveč.
- **Brez vlog.** Naloga zahteva enega uporabnika, zato registracije in upravljanja vlog ni. Novega skrbnika ustvarite z `php bin/create-admin.php "Ime" ime@example.com`.

---

## Porabljen čas

Približno **1,5 ure**.
