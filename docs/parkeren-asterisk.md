# Parkeerplaatsen met Asterisk, Yealink en Cisco

Deze handleiding zet een parking lot op in Asterisk (13 of hoger, met
`res_parking` en PJSIP) en stelt Yealink- en Cisco-toestellen zo in dat
iedereen gesprekken kan parkeren, zien welke plaatsen bezet zijn en een
gesprek kan ophalen.

| Nummer | Functie |
|---|---|
| `700` | Doorverbinden naar 700 = parkeren; Asterisk kiest een vrije plaats |
| `701` – `710` | De parkeerplaatsen; bel het nummer om het gesprek op te halen |
| `#72` | DTMF-code tijdens een gesprek = parkeren |

Een geparkeerd gesprek dat na 120 seconden niet is opgehaald, gaat terug naar
het toestel dat het parkeerde.

Alle bestanden staan in deze repo:

| Bestand | Waarvoor |
|---|---|
| `asterisk/res_parking.conf` | De parking lot zelf |
| `asterisk/features.conf` | DTMF-code `#72` |
| `asterisk/extensions_parking.conf` | Dialplan: parkeerplaatsen bereikbaar maken |
| `asterisk/pjsip_parking.conf` | Instellingen per toestel (DTMF, BLF) |
| `asterisk/manager_parking.conf` | Optioneel: AMI-gebruiker voor de XML app |
| `provisioning/yealink-parking.cfg` | Provisioning voor Yealink |
| `provisioning/cisco-mpp-parking.xml` | Provisioning voor Cisco MPP |

## 1. Asterisk instellen

### 1.1 Parking lot

Kopieer `asterisk/res_parking.conf` naar `/etc/asterisk/res_parking.conf` (of
neem de `[default]`-sectie over in je bestaande bestand) en laad de module:

```sh
asterisk -rx "module reload res_parking.so"
asterisk -rx "parking show"
```

`parking show` moet `Parking Extension : 700` en `Parking Spaces : 701-710`
laten zien. Asterisk maakt zelf de context `[parkedcalls]` aan met 700,
701–710 en (door `parkinghints = yes`) de hints `park:701@parkedcalls` enz.
voor de BLF-lampjes:

```sh
asterisk -rx "dialplan show parkedcalls"
```

> Gebruik je FreePBX? Dan zit parkeren al in de module **Parking Lot**
> (Applications → Parking). Stel daar dezelfde nummers in en sla stap 1.1 tot
> en met 1.3 over; de instructies voor de telefoons hieronder gelden ook.

### 1.2 Dialplan

Voeg in `/etc/asterisk/extensions.conf` toe:

```
#include extensions_parking.conf
```

Kopieer `asterisk/extensions_parking.conf` naar `/etc/asterisk/` en zet in de
context van je toestellen (de `context =` van het pjsip-endpoint, in de
voorbeelden `[intern]`):

```
[intern]
include => parking-intern
```

Wil je kunnen parkeren met `#72`, voeg dan aan de `Dial()`-regels de opties
`k` (de gebelde mag parkeren) en `K` (de beller mag parkeren) toe:

```
exten => _2XX,1,Dial(PJSIP/${EXTEN},30,kK)
```

Voor inkomende gesprekken van de trunk is `k` genoeg (de medewerker die
opneemt mag parkeren). Herlaad met `asterisk -rx "dialplan reload"`.

### 1.3 DTMF-code #72

Voeg uit `asterisk/features.conf` de regel `parkcall => #72` toe aan de
sectie `[featuremap]` van `/etc/asterisk/features.conf` en herlaad:

```sh
asterisk -rx "module reload features"
asterisk -rx "features show" | grep -i park
```

### 1.4 Toestellen (PJSIP)

Controleer per endpoint in `pjsip.conf` (zie `asterisk/pjsip_parking.conf`):

- `context` is de context met `include => parking-intern`;
- `dtmf_mode = rfc4733`, anders komt `#72` niet aan;
- `allow_subscribe = yes` (standaard), nodig voor de BLF-toetsen.

Herlaad met `asterisk -rx "pjsip reload"`.

### 1.5 Optioneel: live bezetting in de Yealink XML app

`parking.php` kan per plaats "vrij" of "bezet: Jan Jansen (0612345678)"
tonen. Daarvoor vraagt de app de bezetting op bij de Asterisk Manager
Interface (AMI).

1. Voeg de gebruiker uit `asterisk/manager_parking.conf` toe aan
   `/etc/asterisk/manager.conf`, kies een eigen wachtwoord en zorg dat in
   `[general]` `enabled = yes` staat. Herlaad: `asterisk -rx "manager reload"`.
2. Zet in `config.php` van de XML app:

   ```php
   'status_url' => 'ami://yealink-parking:KiesEenSterkWachtwoord@127.0.0.1:5038/default',
   ```

   Het laatste deel (`default`) is de naam van de parking lot. Staat er een
   `@`, `:` of `/` in het wachtwoord, schrijf die dan als `%40`, `%3A` of
   `%2F`.

Draait de webserver niet op de Asterisk-server, vervang dan `127.0.0.1` door
het adres van Asterisk en zet het adres van de webserver in `permit`. Zet AMI
(poort 5038) nooit open naar internet. Is AMI niet bereikbaar, dan toont de app
de plaatsen gewoon zonder bezetting.

### 1.6 Testen vanaf de Asterisk-console

```sh
asterisk -rvvv
```

Bel van toestel 201 naar 202, verbind door naar 700 en kijk:

```
*CLI> parking show default
```

Bel daarna vanaf een ander toestel 701: het gesprek wordt opgehaald.

## 2. Gesprekken parkeren en ophalen (werkt op elk toestel)

Deze manieren werken zonder extra instellingen op de telefoon:

| Wat | Hoe |
|---|---|
| Parkeren en de plaats horen | Druk tijdens het gesprek op **Transfer**, kies `700`, luister naar de plaats ("zeven nul een") en druk nog eens op **Transfer** (of hang op) |
| Snel parkeren | Toets tijdens het gesprek `#72` in; je hoort de plaats en je wordt afgemeld |
| Blind parkeren | **Transfer**/**BlindXfer** naar `700` en direct ophangen; de plaats wordt niet genoemd, maar is te zien op de BLF-toetsen of in de XML app |
| Ophalen | Bel het nummer van de plaats, bv. `701` |

De instellingen hieronder maken dit makkelijker met toetsen en lampjes.

## 3. Yealink instellen

Dit geldt voor de T4x/T5x-serie (T46, T48, T54, T57 enz.). De toetsnummers in de
voorbeelden zijn 1 t/m 12; kies toetsen die nog vrij zijn.

### 3.1 Via provisioning

Neem de regels uit `provisioning/yealink-parking.cfg` over in
`y000000000000.cfg` (alle toestellen) of `<mac>.cfg` (één toestel) en pas het
IP-adres van de XML app aan. Herstart daarna het toestel of druk in de
webinterface op **Settings → Auto Provision → Autoprovision Now**.

Wat de regels doen:

| Toets | Type | Waarde | Functie |
|---|---|---|---|
| 1 | `27` XML Browser | `http://…/parking.php` | Overzicht van alle plaatsen, ophalen met **Ophalen** |
| 2 | `11` DTMF | `#72` | Parkeren tijdens een gesprek |
| 3–12 | `16` BLF | `701` … `710` | Lampje per plaats; indrukken = ophalen |

### 3.2 Via de webinterface

Open `http://<ip-van-de-telefoon>` → **Dsskey** → **Line Key**:

1. **BLF per plaats**: Type `BLF`, Value `701`, Label `Park 701`, Line
   `Line 1`. Herhaal voor 702 t/m 710.
2. **Parkeertoets**: Type `DTMF`, Value `#72`, Label `Park`.
3. **Overzicht** (optioneel): Type `XML Browser`, Value
   `http://192.168.1.10/yealink/parking.php`, Label `Parkeren`.

Controleer onder **Account → Advanced** dat **DTMF Type** op `RFC2833` staat.

### 3.3 Gebruik

- **Parkeren**: druk tijdens het gesprek op **Park**. Je hoort de plaats,
  daarna is jouw kant van het gesprek verbroken. Het BLF-lampje van die plaats
  wordt rood.
- **Ophalen**: druk op een rode BLF-toets, of open **Parkeren** en kies de
  plaats met **Ophalen**.
- Werkt de **Park**-toets niet (geen DTMF via RFC 2833 of geen `kK` in de
  dialplan), gebruik dan het alternatief onderaan `yealink-parking.cfg`: een
  toets van het type `Transfer` naar `700`.

## 4. Cisco instellen

Deze instructies gelden voor Cisco IP Phone 6800, 7800 en 8800 met
**multiplatform-firmware (MPP / 3PCC)**, de firmware waarmee Cisco-toestellen
zich bij Asterisk aanmelden. De oudere SPA 300/500-serie gebruikt dezelfde
instellingen (BLF via de lijntoetsen of de attendant console).

> Toestellen met **enterprise-firmware** (CUCM, `SEP<mac>.cnf.xml`) ondersteunen
> geen BLF op deze manier. Op die toestellen werkt parkeren met **Transfer**
> naar `700` of `#72`, en ophalen door het plaatsnummer te bellen (zie stap 2).

### 4.1 Via provisioning

Neem de elementen uit `provisioning/cisco-mpp-parking.xml` over in het
profiel van het toestel. Lijntoets 1 blijft je eigen lijn; de voorbeelden
gebruiken toets 2 t/m 4 voor plaats 701 t/m 703. Een 7841 heeft 4
lijntoetsen, een 8851/8861 heeft er 10; voor meer plaatsen gebruik je een
uitbreidingsmodule (KEM).

### 4.2 Via de webinterface

Open `http://<ip-van-de-telefoon>/admin/advanced` (**Admin Login → advanced**).

1. **DTMF**: **Voice → Ext 1 → Audio Configuration → DTMF Tx Method** = `AVT`.
2. **BLF per plaats**: **Voice → Phone → Line Key 2**:
   - Extension: `Disabled`
   - Short Name: `Park 701`
   - Extended Function: `fnc=blf+sd;sub=701@$PROXY;ext=701@$PROXY;nme=Park 701`

   Herhaal voor de andere toetsen en plaatsen.
3. **Park-softkey**: **Voice → Phone → Programmable Softkeys**:
   - Programmable Softkey Enable: `Yes`
   - PSK 1: `fnc=dtmf;ext=#72;nme=Park`
   - Voeg `psk1` toe aan **Connected Key List**, bv.
     `hold|endcall|xfer|conf|bxfer|psk1`

   Gebruik deze PSK in plaats van de ingebouwde softkey `park`: die volgt de
   parkeermethode van BroadWorks en werkt met Asterisk meestal niet goed.
4. Klik op **Submit All Changes**; de telefoon herstart.

### 4.3 Gebruik

- **Parkeren**: druk tijdens het gesprek op de softkey **Park** (eventueel via
  **Meer**). Je hoort de plaats en je kant van het gesprek wordt verbroken.
  Zonder softkey: **Transfer**, `700`, plaats beluisteren, **Transfer**.
- **Ophalen**: druk op de rode lijntoets van de plaats, of bel het nummer van
  de plaats.

## 5. Problemen oplossen

| Probleem | Oorzaak en oplossing |
|---|---|
| `700` geeft "nummer onbekend" | De context van het toestel bevat `include => parking-intern` niet, of `res_parking` is niet geladen (`module show like parking`) |
| BLF-lampjes blijven uit of knipperen | Controleer `pjsip show subscriptions inbound`; de hint moet in de context van het toestel te vinden zijn (`dialplan show 701@intern`) en `parkinghints = yes` moet aan staan |
| `#72` doet niets | DTMF niet via RFC 2833/AVT, of de opties `k`/`K` ontbreken in `Dial()`. Test met `core set verbose 5` en kijk of Asterisk de DTMF ziet |
| Ophalen geeft stilte of ophangen | De plaats is leeg (al opgehaald of teruggestuurd na de parkeertijd) |
| XML app toont geen bezetting | Test AMI met `telnet 127.0.0.1 5038`; controleer gebruiker, wachtwoord, `permit` en `write = call` in `manager.conf` |
| Gesprek komt na 2 minuten terug | Zo bedoeld (`parkingtime = 120`); pas aan in `res_parking.conf` |
