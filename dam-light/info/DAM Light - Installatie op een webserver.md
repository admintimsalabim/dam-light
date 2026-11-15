# DAM Light – Installatie op een webserver

Nov 15, 2026 · @Tim Geurtzen

## Inleiding

Met deze instructie zet je DAM Light in 20 tot 30 minuten op een gewone webhost, bereikbaar via een eigen (sub)domein met HTTPS. Zo kun je het portaal ook laten zien op netwerken die je NAS-adres blokkeren.

Er zijn twee routes:

- Route A – shared hosting (aanbevolen): een webhostingpakket met een beheerpaneel zoals cPanel, DirectAdmin of Plesk. Geen kennis van servers nodig. Stap 1 t/m 6 gaan over deze route.

- Route B – eigen VPS: een virtuele server waarop je zelf Nginx of Apache beheert. Zie het hoofdstuk Alternatief: eigen VPS.

Tip: gebruik een subdomein, bijvoorbeeld brandportal.jouwdomein.nl, met een geldig HTTPS-certificaat. Bedrijfsnetwerken blokkeren vaak adressen met een IP-nummer, een afwijkende poort (zoals :8101) of een gratis dynamisch DNS-adres. Een normaal domein met HTTPS heeft daar veel minder last van.

## Benodigdheden

Controleer deze punten bij je hostingpakket voordat je begint. Vrijwel elk modern pakket voldoet.

Je hebt verder nodig: de nieuwste dam-light.zip, toegang tot het hostingpaneel, en eventueel een FTP-programma zoals FileZilla.

## Stap 1 – Bestanden voorbereiden

Pak dam-light.zip uit op je computer en pas alleen config.php aan. Vul de databasegegevens pas in na stap 2, als je ze hebt.

```php
'demo_mode' => true,        // false = demo-accounts niet tonen op het inlogscherm

'db' => [
    'driver' => 'mysql',    // belangrijk: niet 'sqlite'
    'mysql'  => [
        'host'    => 'localhost',          // bij shared hosting bijna altijd localhost
        'port'    => 3306,
        'dbname'  => 'account_damlight',   // volledige naam, inclusief voorvoegsel
        'user'    => 'account_damuser',
        'pass'    => 'jouw-sterke-wachtwoord',
        'charset' => 'utf8mb4',
    ],
],
```

Laat base_url leeg ('') als het portaal op de hoofdmap van een (sub)domein komt, zoals in deze instructie.

Wat upload je?

- Wel: de hele map, inclusief demo-content/ (voor de demo-inhoud) en public/install.php (voor de installatie).

- Niet: config.local.php (als je die lokaal hebt) en een bestaande storage/dam.sqlite. Bij een nieuwe installatie begint storage/ leeg.

Wil je je huidige NAS-installatie inclusief eigen uploads en huisstijl verhuizen? Dat kan ook: zie Bijwerken naar een nieuwe versie, onderaan.

## Stap 2 – Database aanmaken

Maak in het hostingpaneel een lege database en een databasegebruiker aan, en koppel ze met alle rechten.

- Open in het paneel MySQL-databases (cPanel), MySQL-beheer (DirectAdmin) of Databases (Plesk).

- Maak een database aan, bijvoorbeeld damlight. Veel hosts zetten er je accountnaam voor: account_damlight.

- Maak een gebruiker aan, bijvoorbeeld damuser, met een sterk wachtwoord. Ook hier komt vaak een voorvoegsel voor.

- Koppel de gebruiker aan de database met alle rechten (All privileges).

- Noteer de volledige namen (met voorvoegsel), het wachtwoord en de host. Die host is bijna altijd localhost; staat er in het paneel een andere servernaam, gebruik die.

- Vul deze gegevens in config.php in (stap 1).

De tabellen maak je niet zelf aan; dat doet de installatiepagina in stap 5. Gebruik bij voorkeur de collatie utf8mb4_unicode_ci als het paneel ernaar vraagt.

## Stap 3 – Uploaden en de documentmap instellen

Zet de map op de server en laat het subdomein naar de map public/ wijzen. Alleen die map hoort via internet bereikbaar te zijn; app/, storage/ en config.php niet.

Uploaden

- Snelst: upload dam-light.zip via de Bestandsbeheerder van het paneel en kies daar Uitpakken (Extract). Hernoem de map daarna eventueel naar dam.

- Of: upload de uitgepakte map met FileZilla (FTP/SFTP).

- Zet de map naast public_html (of httpdocs), niet erin, bijvoorbeeld /home/account/dam/. Kan dat bij jouw host niet, zie de variant hieronder.

Subdomein aanmaken met documentmap op public/

Variant: documentmap kan niet worden ingesteld

Zet de inhoud van de map direct in de map van het subdomein (bijvoorbeeld public_html/brandportal/ of domains/…/public_html/). De meegeleverde .htaccess stuurt dan alles door naar public/ en blokkeert config.php, app/, storage/ en bin/. Dit werkt alleen op Apache (of LiteSpeed) met .htaccess-ondersteuning, wat bij vrijwel alle shared hosting zo is. Installeer het portaal in deze variant altijd op de hoofdmap van een subdomein, niet in een submap zoals jouwdomein.nl/dam/.

Controle na het uploaden: https://brandportal.jouwdomein.nl/config.php moet een lege pagina of een foutmelding geven, en https://brandportal.jouwdomein.nl/storage/ een 403 of 404. Zie je de inhoud van een bestand of een bestandslijst, dan staat de documentmap niet goed.

## Stap 4 – Rechten en PHP-instellingen

Op shared hosting draait PHP meestal onder je eigen account. Rechten aanpassen is dan zelden nodig, maar de PHP-versie en de uploadlimiet wel.

Rechten

- storage/ moet schrijfbaar zijn voor PHP. Zet de map via de Bestandsbeheerder of FileZilla (rechtermuisknop → Bestandsrechten) op 755; geeft de installatiepagina een ❌ bij “Schrijfbaar”, probeer dan 775.

- Gebruik nooit 777.

- Andere mappen: 755. Bestanden: 644.

PHP-versie en extensies

Kies in het paneel PHP 8.3 (of minimaal 8.2) voor het subdomein. Zet daar ook pdo_mysql, fileinfo en zip aan als ze uit staan. In cPanel heet dit vaak MultiPHP Manager of Select PHP Version.

Uploadlimiet

Standaard staat de limiet vaak op 2 tot 64 MB; drukbestanden zijn groter. Pas in het paneel (MultiPHP INI Editor of PHP-instellingen) deze waarden aan:

```php
upload_max_filesize = 256M
post_max_size = 256M
memory_limit = 256M
max_execution_time = 300
```

Geen paneeloptie? Zet dezelfde regels in een bestand .user.ini in de map public/. Het kan tot 5 minuten duren voordat de host dat oppikt. Sommige hosts hebben een vaste bovengrens; die staat in hun documentatie.

## Stap 5 – Installeren

De installatiepagina maakt de tabellen aan en laadt de demo-inhoud in één klik.

- Ga naar https://brandportal.jouwdomein.nl/install.php.

- Controleer dat er Database: mysql staat. Staat er sqlite, dan is 'driver' in config.php niet aangepast.

- Loop de controlelijst na: alle punten moeten ✅ zijn. Bij een ❌ staat erachter wat er mis is; los dat op en ververs de pagina.

- Laat Demo-inhoud laden aangevinkt en klik op Installeren.

- Je ziet een overzicht: 11 demo-gebruikers, 19 assets (waarvan 3 stockfoto’s) en de gesimuleerde downloadhistorie.

- Verwijder daarna public/install.php van de server.

Inloggen kan daarna met de demo-accounts:

Zet daarna onder Beheer → Huisstijl je eigen bedrijfsnaam, kleuren en logo. Die instellingen gaan niet mee vanaf de NAS; ze staan in de database.

## Stap 6 – Beveiligen en afronden

Het portaal staat nu op internet; met deze stappen houd je de demo veilig.

- ☐ HTTPS aan: zet in het paneel een gratis certificaat aan (Let’s Encrypt / AutoSSL) en kies HTTPS afdwingen of Forceer HTTPS.

- ☐ public/install.php verwijderd.

- ☐ Demo-accounts afschermen: zet 'demo_mode' => false in config.php zodra iemand anders dan jij de link krijgt. Anders staan de wachtwoorden op het inlogscherm.

- ☐ Wachtwoorden vervangen: gebruik bij Beheer → Gebruikers de knop Reset ww voor de beheerder en de accounts die je laat zien. Je krijgt een nieuw tijdelijk wachtwoord te zien. Er is in deze demo geen scherm om je eigen wachtwoord te wijzigen.

- ☐ Controles uit stap 3: config.php en storage/ zijn niet bereikbaar via de browser.

- ☐ Test vanaf het werknetwerk: open de link op je werklaptop. Wordt hij nog steeds geblokkeerd, zie Problemen oplossen.

Deel de link alleen met wie hem nodig heeft. De demo heeft geen tweestapsverificatie en is niet bedoeld voor echte, vertrouwelijke bestanden.

## Alternatief: eigen VPS (Ubuntu 24.04)

Op een eigen server installeer je PHP, MariaDB en Nginx zelf; de app-stappen blijven gelijk. Vervang brandportal.jouwdomein.nl door je eigen subdomein en laat dat eerst naar het IP-adres van de server wijzen (DNS A-record).

1. Pakketten installeren

```php
sudo apt update
sudo apt install -y nginx mariadb-server php8.3-fpm php8.3-mysql php8.3-zip php8.3-mbstring unzip certbot python3-certbot-nginx
```

2. Database

```php
sudo mysql -e "CREATE DATABASE dam_light CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'dam_user'@'localhost' IDENTIFIED BY 'sterk-wachtwoord';
GRANT ALL ON dam_light.* TO 'dam_user'@'localhost';"
```

3. Bestanden en rechten

```php
sudo unzip dam-light.zip -d /var/www/ && sudo mv /var/www/dam-light /var/www/dam
# config.php aanpassen (driver mysql, host localhost, gegevens van stap 2)
sudo chown -R root:root /var/www/dam
sudo chown -R www-data:www-data /var/www/dam/storage
sudo chmod -R 775 /var/www/dam/storage
```

4. Nginx – bestand /etc/nginx/sites-available/dam:

```php
server {
    listen 80;
    server_name brandportal.jouwdomein.nl;
    root /var/www/dam/public;
    index index.php;
    client_max_body_size 256M;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ /\. { deny all; }
}
```

```php
sudo ln -s /etc/nginx/sites-available/dam /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d brandportal.jouwdomein.nl    # HTTPS via Let's Encrypt
```

5. Uploadlimiet PHP – zet de regels uit stap 4 in /etc/php/8.3/fpm/conf.d/99-dam.ini en voer sudo systemctl restart php8.3-fpm uit.

6. Installeren – via de browser (stap 5) of op de server: sudo -u www-data php /var/www/dam/bin/install.php. Verwijder daarna public/install.php.

Gebruik je Apache in plaats van Nginx? Zet dan DocumentRoot /var/www/dam/public in de virtual host, met AllowOverride All en a2enmod rewrite.

## Problemen oplossen

De meeste problemen komen van de documentmap, de databasegegevens of de rechten op storage/. Begin bij twijfel altijd bij de controlelijst op install.php (zolang die er nog staat) of het foutlog in het hostingpaneel.

## Bijwerken en verhuizen

Bij een nieuwe versie vervang je alleen de programmabestanden; de database werkt zichzelf bij bij de eerste paginaweergave.

Nieuwe versie installeren

- Maak eerst een back-up: exporteer de database via phpMyAdmin (Exporteren → Snel → SQL) en download de map storage/.

- Upload alles uit de nieuwe zip, behalve config.php, de map storage/ en public/install.php.

- Open het portaal en log in. Klaar; je hoeft niets opnieuw te installeren.

Je NAS-installatie verhuizen (eigen uploads en huisstijl meenemen)

Alleen nodig als je op de NAS eigen bestanden of instellingen hebt toegevoegd die je wilt houden. Anders is een verse installatie (stap 1 t/m 6) sneller.

- Exporteer op de NAS de database via phpMyAdmin (Exporteren → SQL).

- Kopieer van de NAS de map storage/ (met files/, previews/ en branding/).

- Doe op de nieuwe server stap 1 t/m 4, maar sla de installatie (stap 5) over.

- Importeer het SQL-bestand via phpMyAdmin (Importeren) in de nieuwe, lege database.

- Upload de gekopieerde storage/-map en controleer dat hij schrijfbaar is (stap 4).

- Verwijder public/install.php, open het portaal en log in met je bestaande accounts.

### Tabel 1

| Onderdeel | Eis | Waar controleer je het |
| --- | --- | --- |
| PHP | 8.2 of nieuwer (8.3 aanbevolen) | Paneel: PHP-versie, MultiPHP Manager of PHP Selector |
| PHP-extensies | pdo_mysql, fileinfo, zip (zip alleen voor “Alles (ZIP)”) | Zelfde plek; de installatiepagina controleert dit ook |
| Database | MySQL 5.7+ of MariaDB 10.4+ | Paneel: MySQL-databases of phpMyAdmin |
| Webserver | Apache met .htaccess, of de mogelijkheid om de documentmap (document root) in te stellen | Bijna altijd standaard |
| HTTPS | Gratis certificaat (Let’s Encrypt / AutoSSL) | Paneel: SSL/TLS |
| Opslag | Minimaal 500 MB; meer naarmate je meer bestanden uploadt | Pakketoverzicht |
| Uploadlimiet | Bij voorkeur 256 MB per bestand voor drukbestanden | Zie stap 4 |

### Tabel 2

| Paneel | Waar | Documentmap invullen |
| --- | --- | --- |
| cPanel | Domeinen → Domein maken (of Subdomeinen) | dam/public |
| Plesk | Websites & Domeinen → Subdomein toevoegen, later Hostinginstellingen | dam/public (of httpdocs/dam/public) |
| DirectAdmin | Subdomeinbeheer | Lukt een eigen documentmap niet? Gebruik de variant hieronder |

### Tabel 3

| Rol | E-mail | Wachtwoord |
| --- | --- | --- |
| Beheerder (HQ) | admin@demo.local | Admin123! |
| Dochterkantoor | kantoor.de@demo.local | Demo123! |
| Salespartner | partner@demo.local | Demo123! |

### Tabel 4

| Wat je ziet | Waarschijnlijke oorzaak | Oplossing |
| --- | --- | --- |
| Installatiepagina zegt “Database: sqlite” | 'driver' staat nog op sqlite | In config.php op 'mysql' zetten |
| ❌ bij Databaseverbinding | Naam, gebruiker of wachtwoord zonder voorvoegsel, of verkeerde host | Volledige namen uit het paneel overnemen; host meestal localhost |
| ❌ bij “Schrijfbaar: storage” | Map niet schrijfbaar voor PHP | storage/ op 755, anders 775 (stap 4) |
| HTTP 500 of witte pagina | PHP-versie te oud, of een extensie ontbreekt | PHP 8.2+ kiezen en pdo_mysql aanzetten; foutlog in het paneel bekijken |
| Pagina zonder opmaak, of 404 op login.php | Documentmap wijst niet naar public/ en .htaccess werkt niet | Documentmap op …/public zetten (stap 3) |
| “Too many redirects” | Oude .htaccess uit een eerdere versie | De .htaccess uit de nieuwste zip gebruiken |
| config.php toont tekst, of storage/ toont een bestandslijst | Hele map is openbaar zonder .htaccess-bescherming | Documentmap op public/ zetten; daarna opnieuw controleren |
| Upload van groot bestand mislukt of “413” | Uploadlimiet PHP of webserver te laag | Waarden uit stap 4 instellen; host om hogere limiet vragen |
| Knop “Alles (ZIP)” ontbreekt | Extensie zip staat uit | In de PHP-instellingen aanzetten |
| Link wordt op het werk nog steeds geblokkeerd | Nieuw domein nog niet geclassificeerd door het filter | IT vragen het subdomein toe te staan, of een subdomein van het bedrijfsdomein gebruiken |
