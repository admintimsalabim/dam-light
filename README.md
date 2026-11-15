# DAM Light – Brand Portal

Een lichtgewicht Digital Asset Management-portaal om aan het management te laten zien hoe een DAM werkt:
kantoren en salespartners loggen in, vinden visueel het juiste middel (logo, roll-up, brochure, beurswand…)
en downloaden direct het juiste bestand voor **drukwerk (CMYK)**, **steunkleur (PMS)** of **digitaal (RGB)**.

De demo bevat een fictief merk (**NORVIQ**) met echte, werkende bestanden: vector-logo's in CMYK/PMS/RGB,
drukklare roll-ups van 850 × 2000 mm met afloop en snijtekens, een brochure, flyer, beurswand, social banners en
een gesimuleerde downloadhistorie, zodat het dashboard er meteen gevuld uitziet.

## Wat zit erin

**Voor gebruikers (kantoren en partners)**

- Startpagina met zoeken, populaire snelkoppelingen en categorietegels
- Bibliotheek met filters op categorie, toepassing (CMYK/PMS/RGB/Office) en taal, plus sorteren
- Detailpagina met groot voorbeeld, uitleg "Welk bestand heb ik nodig?", downloads gegroepeerd per toepassing en alles als ZIP
- Bestanden krijgen bij het downloaden een nette naam, bijvoorbeeld `norviq-roll-up-beurs-benelux_drukklare-pdf-cmyk.pdf`

**Twee talen (Nederlands / Engels)**

- Elke gebruiker heeft een eigen interfacetaal (in te stellen door de beheerder) en kan zelf wisselen via **NL / EN** rechtsboven; die keuze wordt bewaard
- Ook de inhoud is tweetalig: titel, omschrijving en bestandslabels hebben een optioneel Engels veld. Leeg = de Nederlandse tekst wordt getoond
- Bestandsnamen van downloads volgen de taal van de gebruiker
- Extra taal toevoegen: kopieer `lang/en.php` naar bijvoorbeeld `lang/de.php`, vertaal de teksten en voeg de taal toe aan `languages` in `config.php`

**Gebruiksrechten (eigen beeld / stockfoto)**

- Per asset stel je in of het een *eigen beeld* is (iedereen mag het gebruiken) of een *stockfoto* (alleen de eigen organisatie, inclusief dochterkantoren)
- Distributeurs/salespartners zien stockfoto's wel, met een watermerk, maar kunnen ze niet downloaden. Dit wordt ook op de server afgedwongen, dus ook een directe downloadlink werkt niet
- Ze krijgen een duidelijke melding, het stock-ID om te kopiëren en een knop naar de foto bij de leverancier (bijv. Adobe Stock)
- Kantoren zien een melding dat de foto alleen intern gebruikt mag worden en niet doorgestuurd mag worden
- Optionele vervaldatum van de licentie: daarna kan alleen de beheerder de foto nog downloaden. Het dashboard waarschuwt 90 dagen van tevoren
- Filter "Gebruiksrechten" in de bibliotheek

**Voor beheer (HQ)**

- Dashboard: downloads per dag, per toepassing, meest gedownload, actiefste organisaties, slapende accounts
- Assets beheren: gegevens, voorbeeldafbeelding, meerdere bestanden tegelijk uploaden (CMYK/PMS/RGB wordt aan de bestandsnaam herkend), concept/gepubliceerd
- Zichtbaarheid per asset: *iedereen* of *alleen eigen kantoren*
- Gebruikers: rollen (beheerder, dochterkantoor, salespartner), vervaldatum voor tijdelijke partners, (de)activeren, wachtwoord resetten
- Downloadlog met export naar Excel (CSV)
- Huisstijl: bedrijfsnaam, portaalnaam, hoofd- en accentkleur en het logo in de kopbalk aanpassen, met live voorbeeld en een knop om terug te gaan naar de standaard

## Demo-accounts

| Rol            | E-mail                | Wachtwoord |
|----------------|-----------------------|------------|
| Beheerder (HQ) | admin@demo.local      | Admin123!  |
| Dochterkantoor | kantoor.de@demo.local | Demo123!   |
| Salespartner   | partner@demo.local    | Demo123!   |

Tip voor de presentatie: log eerst in als partner (die ziet de beurswand niet, want die is "alleen kantoren"),
daarna als dochterkantoor, en sluit af met het beheerdashboard.

## Snel starten op je eigen laptop (aanbevolen voor een demo)

Je hebt PHP 8.2 of nieuwer nodig, met de extensies `pdo_sqlite` (of `pdo_mysql`), `fileinfo` en `zip`.

```bash
cd dam-light
php bin/install.php          # maakt de SQLite-database en laadt de demo-inhoud
php -S localhost:8000 -t public
```

Open daarna http://localhost:8000.

- Opnieuw beginnen: `php bin/install.php --fresh`
- Zonder demo-inhoud, alleen één beheerder: `php bin/install.php --fresh --no-demo`

## Op een webserver (Apache/Nginx)

1. Upload de hele map. Stel de **document root in op** `public/`. Kan dat niet, dan stuurt de meegeleverde `.htaccess` alles door naar `public/`.
2. Zorg dat de webserver kan schrijven in `storage/`.
3. Geen SSH? Ga naar `/install.php`, klik op *Installeren* en **verwijder daarna** `public/install.php`.
4. Zet in `config.php` de optie `demo_mode` op `false`, zodat de demo-accounts niet meer op het inlogscherm staan.

Uploads groter dan 2 MB? Verhoog dan `upload_max_filesize` en `post_max_size` in `php.ini`, bijvoorbeeld naar `256M`.

## MySQL / MariaDB in plaats van SQLite

Zet in `config.php` `'driver' => 'mysql'` en vul de gegevens in. Maak eerst een lege database aan:

```sql
CREATE DATABASE dam_light CHARACTER SET utf8mb4;
CREATE USER 'dam_user'@'localhost' IDENTIFIED BY 'sterk-wachtwoord';
GRANT ALL ON dam_light.* TO 'dam_user'@'localhost';
```

Draai daarna `php bin/install.php`. De app is getest op zowel SQLite als MariaDB.

## Mappenstructuur

```
config.php          instellingen (database, opslag, talen)
app/                logica: bootstrap, layout, bestanden, databaseschema, installer, talen, huisstijl
lang/               vertalingen (en.php)
bin/install.php     installatie via de commandline
public/             webroot: pagina's, admin/, assets/style.css
storage/            database (SQLite), geüploade bestanden en voorbeelden (niet publiek bereikbaar)
demo-content/       bronbestanden van het demo-merk NORVIQ
```

Bestanden staan buiten de webroot en worden alleen via `download.php` uitgeleverd na een rechtencontrole. Elke download wordt gelogd.

## Eigen huisstijl

Alles via **Beheer → Huisstijl**: naam, kleuren en logo. Wijzigingen gelden direct voor iedereen, ook op de inlogpagina.
Het logo wordt opgeslagen in `storage/branding/`. SVG-logo's worden in een afgeschermde modus geserveerd, zodat eventuele scripts erin niet worden uitgevoerd.

## Bijwerken vanaf een eerdere versie

Vervang de programmabestanden, maar laat `config.php` en de map `storage/` staan. Bij de eerste paginaweergave werkt de app de database zelf bij
(nieuwe kolommen voor taal en Engelse teksten en een tabel `settings`). Je hoeft niets opnieuw te installeren.

## Bewust níet in deze light-versie

Dit is een demonstratiemodel. Voor productie zou je verwachten (en dit bieden Bynder, Canto en vergelijkbare pakketten standaard):

- MFA en/of single sign-on (Entra ID voor eigen kantoren, gastaccounts voor partners)
- Automatische previews en conversie (bijvoorbeeld PDF → JPG, of een logo direct exporteren in elk formaat)
- Versiebeheer, vervaldatums en licentierechten per bestand
- Goedkeuringsworkflows, bijvoorbeeld een lokaal kantoor dat een aangepaste roll-up laat goedkeuren
- Templates die je zelf bewerkt ("brand templates"), zoals een roll-up met eigen standnummer
- Meertaligheid van de interface, wachtwoord-vergeten via e-mail, en virusscan op uploads
- Integraties met InDesign, Figma, Office, het CMS en de PIM

Juist deze lijst helpt in het gesprek met het management: de demo laat de waarde zien, en de lijst laat zien wat een professioneel pakket daarbovenop biedt.
