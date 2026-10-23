# Yealink T48 XML app

Een XML Browser-applicatie voor de Yealink T48 (T48G / T48S / T48U) en andere
Yealink-toestellen die het Yealink XML-formaat ondersteunen. De app draait op
elke webserver met PHP 8.0 of hoger en heeft geen database nodig.

## Functies

| Scherm | Bestand | Yealink-object |
|---|---|---|
| Hoofdmenu | `index.php` | `YealinkIPPhoneTextMenu` |
| Telefoonboek (met pagina's) | `phonebook.php` | `YealinkIPPhoneTextMenu` |
| Contact bellen | `contact.php` | `YealinkIPPhoneDirectory` |
| Zoeken op naam of nummer | `search.php` | `YealinkIPPhoneInputScreen` |
| Afdelingen | `groups.php` | `YealinkIPPhoneTextMenu` |
| Datum, tijd en toestelinfo | `info.php` | `YealinkIPPhoneTextScreen` |
| Remote phonebook | `remote-phonebook.php` | `YealinkIPPhoneBook` |

## Installatie

1. Kopieer de map naar je webserver, bv. naar `/var/www/html/yealink`.
2. Pas `config.php` aan (naam van de app, timeout, contacten per pagina).
   Werkt de automatische URL-detectie niet achter een proxy, vul dan
   `base_url` in, bv. `http://192.168.1.10/yealink`.
3. Zet je contacten in `data/contacts.json`:

   ```json
   [
     {"name": "Anna de Vries", "group": "Verkoop",
      "numbers": {"intern": "201", "mobiel": "+31 6 12345678"}}
   ]
   ```

   Gebruikt je server Apache, dan blokkeren de `.htaccess`-bestanden directe
   toegang tot `data/`, `lib/` en `tests/`. Bij nginx doe je dit met een
   `location`-regel.

Lokaal uitproberen kan met de ingebouwde PHP-server:

```sh
php -S 0.0.0.0:8080
# open http://<ip-van-je-pc>:8080/index.php
```

## Instellen op de T48

### Als toets (DSS key)

Webinterface van de telefoon → **Dsskey** → **Line Key** (of tik lang op een
lege toets op het scherm):

- Type: `XML Browser`
- Label: bv. `Bedrijfsapp`
- Value: `http://192.168.1.10/yealink/index.php`

Of via provisioning (`y0000000000xx.cfg` / `<mac>.cfg`):

```
linekey.10.type = 27
linekey.10.label = Bedrijfsapp
linekey.10.value = http://192.168.1.10/yealink/index.php
```

### Als remote phonebook

Webinterface → **Directory** → **Remote Phone Book**, of via provisioning:

```
remote_phonebook.data.1.url = http://192.168.1.10/yealink/remote-phonebook.php
remote_phonebook.data.1.name = Bedrijf
```

## Testen

```sh
php tests/run.php
```

Het script start een tijdelijke PHP-server, roept alle schermen aan en
controleert dat elk antwoord geldige XML met het juiste Yealink-object is.

## Uitbreiden

De helpers in `lib/Yealink.php` (`yl_text_menu`, `yl_text_screen`,
`yl_input_screen`, `yl_directory`) bouwen de XML op en escapen alle tekst.
Een nieuw scherm is een PHP-bestand dat een van deze functies aanroept en het
resultaat met `yl_send()` verstuurt; voeg het daarna toe aan het menu in
`index.php`.
