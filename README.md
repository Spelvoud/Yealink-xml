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
| Parkeerplaatsen (call park) | `parking.php` | `YealinkIPPhoneDirectory` |
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

## Parkeerplaatsen (parking lot)

`parking.php` toont de parkeerplaatsen van de centrale. Kies een plaats en druk
op **Ophalen** om het geparkeerde gesprek aan te nemen; de telefoon belt dan
`pickup_prefix` + het nummer van de plaats. **Uitleg** laat zien hoe je een
gesprek parkeert (Transfer naar `park_number`).

Stel in `config.php` onder `parking` in:

| Instelling | Standaard | Betekenis |
|---|---|---|
| `first_slot` / `slot_count` | `701` / `10` | Plaatsen 701 t/m 710 |
| `park_number` | `700` | Nummer om naar door te verbinden om te parkeren |
| `pickup_prefix` | leeg | Voorvoegsel voor ophalen, bv. `*88` |
| `status_url` | leeg | Optionele JSON-bron met de bezetting |

Zonder `status_url` worden alle plaatsen getoond zonder bezetting. Met
`status_url` (een URL of een lokaal bestand) toont het scherm per plaats
"vrij" of "bezet" en het aantal bezette plaatsen in de titel. Beide formaten
werken:

```json
{"701": "Jan Jansen (0612345678)", "704": "+31201234567"}
```

```json
["701", "704"]
```

Zo'n bron kun je bijvoorbeeld laten vullen door een script dat
`asterisk -rx "parkedcalls show"` uitleest. Kan de bron niet gelezen worden,
dan toont het scherm de plaatsen zonder bezetting.

Tip: wil je bezetting live zien zonder `status_url`, zet dan op een paar
lijntoetsen van de T48 een BLF naar een parkeerplaats (bv.
`linekey.11.type = 16`, `linekey.11.value = 701`), mits je centrale BLF voor
parkeerplaatsen ondersteunt.

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
