# Parkeerplaatsen met Asterisk, Yealink en Cisco

Deze handleiding zet een parking lot op in Asterisk (met `res_parking`) en
stelt Yealink- en Cisco-toestellen zo in dat iedereen gesprekken kan parkeren,
zien welke plaatsen bezet zijn en een gesprek kan ophalen.

Uitgangspunt:

- **Yealink** meldt zich aan via PJSIP (of chan_sip, dat werkt ook).
- **Cisco** heeft **SIP enterprise-firmware** en meldt zich aan via
  **chan_sip met de [usecallmanager.nz](https://usecallmanager.nz)-patch**.
  Die patch geeft de Cisco-toestellen een echte **Park**-softkey die de
  parkeerplaats op het scherm toont, en BLF-lampjes op de lijntoetsen.

Draaien chan_sip en PJSIP naast elkaar, geef ze dan elk een eigen poort (bv.
chan_sip op 5060 voor Cisco en PJSIP op 5062 voor Yealink).

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
| `asterisk/pjsip_parking.conf` | Instellingen per Yealink-toestel (PJSIP) |
| `asterisk/sip_cisco_parking.conf` | Instellingen per Cisco-toestel (chan_sip met patch) |
| `asterisk/manager_parking.conf` | Optioneel: AMI-gebruiker voor de XML app |
| `provisioning/yealink-parking.cfg` | Provisioning voor Yealink |
| `provisioning/cisco-sepmac-parking.xml` | Fragmenten voor `SEP<MAC>.cnf.xml` en `SoftKeys.xml` |

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
context van je toestellen (de `context =` van het pjsip-endpoint of de
sip-peer, in de voorbeelden `[intern]`):

```
[intern]
include => parking-intern
```

`parking-intern` bevat ook de extensies voor de Cisco-toestellen, gebaseerd op
de pagina *Dialplan Extensions* van usecallmanager.nz:

- `_[x]-cisco-serviceuri-.` haalt het voorvoegsel `x-cisco-serviceuri-` van
  Cisco-functies af. Staat die regel al in je dialplan, laat hem dan weg uit
  `extensions_parking.conf`.
- `park` gebruikt de Park-softkey om te parkeren.
- `_dpark-.` parkeert een gesprek op een vaste plaats via de parkeertoetsen
  (zie 4.2).

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

### 1.4 Yealink-toestellen (PJSIP)

Controleer per endpoint in `pjsip.conf` (zie `asterisk/pjsip_parking.conf`):

- `context` is de context met `include => parking-intern`;
- `dtmf_mode = rfc4733`, anders komt `#72` niet aan;
- `allow_subscribe = yes` (standaard), nodig voor de BLF-toetsen.

Herlaad met `asterisk -rx "pjsip reload"`.

### 1.5 Cisco-toestellen (chan_sip met usecallmanager.nz-patch)

Neem per Cisco-peer in `sip.conf` de instellingen uit
`asterisk/sip_cisco_parking.conf` over (of gebruik de template
`[cisco-parking](!)`):

| Instelling | Waarom |
|---|---|
| `context = intern` | Context met `include => parking-intern` |
| `subscribecontext = intern` | Hier zoekt Asterisk de hints van 701–710 voor de BLF-toetsen |
| `allowsubscribe = yes` | BLF-abonnementen toestaan |
| `subscribe = 701@parkedcalls,…` | Cisco-toestellen sturen zelf geen SUBSCRIBE voor BLF-lijntoetsen. Met `subscribe` stuurt Asterisk de status van die plaatsen toch naar het toestel. Zet hier alle plaatsen waarvoor het toestel een toets heeft |
| `cisco = yes` | Zet de Cisco-functies van de patch aan, waaronder de Park-softkey. Oudere patchversies gebruiken `cisco_usecallmanager = yes` |
| `parkinglot = default` | Parking lot uit `res_parking.conf` |
| `parknotifytimer = 5` | Zo lang (seconden) toont het toestel "geparkeerd op 701" |
| `dtmfmode = rfc2833` | Alleen nodig als je ook `#72` wilt gebruiken |

Herlaad met `asterisk -rx "sip reload"` en controleer met
`asterisk -rx "sip show peer 201"` dat `Parkinglot` op `default` staat.

Met de patch kun je in `res_parking.conf` ook `remindertime` aanzetten: het
Cisco-toestel van wie parkeerde herinnert dan na die tijd aan het gesprek.
Deze optie staat uitgecommentarieerd, omdat `res_parking` niet laadt als je
patchversie de optie niet kent.

### 1.6 Optioneel: live bezetting in de Yealink XML app

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

### 1.7 Testen vanaf de Asterisk-console

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

## 4. Cisco instellen (SIP enterprise-firmware)

Dit geldt voor Cisco-toestellen met SIP enterprise-firmware die via de
usecallmanager.nz-patch op Asterisk draaien (bv. 7942/7962, 7945/7965, 8841,
8851, 8861, 8945, 9971). Je stelt ze in via de bestanden op je TFTP-server:
`SEP<MAC>.cnf.xml` per toestel en een gedeeld `SoftKeys.xml`. Gebruik als
basis de voorbeeldbestanden uit
[usecallmanagernz/tftpboot](https://github.com/usecallmanagernz/tftpboot).

Alle fragmenten staan in `provisioning/cisco-sepmac-parking.xml`.

### 4.1 Park-softkey

1. Zorg dat `SoftKeys.xml` op de TFTP-server staat en dat
   `SEP<MAC>.cnf.xml` ernaar verwijst, binnen `<sipProfile>`:

   ```xml
   <softKeyFile>SoftKeys.xml</softKeyFile>
   ```

2. Zet in `SoftKeys.xml` de softkey `Park` in de set `Connected` (in het
   voorbeeldbestand staat hij er al):

   ```xml
   <softKeySet id="Connected">
     <softKey keyID="Hold" />
     <softKey keyID="EndCall" />
     <softKey keyID="Transfer" />
     <softKey keyID="Confrn" />
     <softKey keyID="Park" />
   </softKeySet>
   ```

3. Optioneel: zet binnen `<vendorConfig>` in `SEP<MAC>.cnf.xml`

   ```xml
   <callParkMonitor>1</callParkMonitor>
   ```

   Dan blijft het geparkeerde gesprek zichtbaar op het toestel van wie
   parkeerde, met een melding als het wordt opgehaald of terugkomt.

Liever een vaste toets dan een softkey? Gebruik dan een lijntoets met
`featureID` `126`. Die doet hetzelfde als de softkey:

```xml
<line button="5">
  <featureID>126</featureID>
  <featureLabel>Park</featureLabel>
</line>
```

Wat er gebeurt: de telefoon stuurt bij **Park** een verzoek naar Asterisk. De
patch parkeert de andere partij in de parking lot uit de extensie `park` (of
uit `parkinglot` van de peer) en stuurt de plaats terug, die het toestel
`parknotifytimer` seconden in beeld toont.

### 4.2 Parkeertoetsen per plaats (BLF Directed Call Park)

Voeg in `SEP<MAC>.cnf.xml` binnen `<sipLines>`, na je eigen lijn
(`button="1"`), per parkeerplaats een toets toe:

```xml
<line button="2">
  <featureID>22</featureID>
  <featureLabel>Park 701</featureLabel>
  <speedDialNumber>701</speedDialNumber>
  <contact>701</contact>
  <retrievalPrefix/>
</line>
```

- `featureID` `22` = BLF Directed Call Park. Het lampje toont alleen vrij of
  bezet. Het toestel vraagt de status niet zelf op: Asterisk stuurt hem door
  de regel `subscribe = 701@parkedcalls,…` bij de peer in `sip.conf` (zie
  1.5). Zonder die regel blijft het lampje uit.
- **Plaats vrij en je zit in een gesprek**: het toestel verbindt het gesprek
  blind door naar `x-cisco-serviceuri-dpark-701`. De dialplan in
  `parking-intern` maakt daar `dpark-701` van en parkeert het gesprek precies
  op 701.
- **Plaats bezet**: indrukken belt `701` en haalt het gesprek op.
  `<retrievalPrefix/>` blijft leeg, want bij Asterisk bel je de plaats zelf.
- Herhaal voor 702, 703 enz., met `button="3"`, `"4"` …, zoveel als het
  toestel (plus eventuele KEM) toetsen heeft.

Volgens de Line Keys-pagina van usecallmanager.nz heeft elke BLF-toets twee
dingen nodig: een `hint` in de dialplan en een `subscribe`-regel in
`sip.conf`. De hints maakt Asterisk zelf aan (`parkinghints = yes` in
`res_parking.conf`); de `subscribe`-regels staan in
`asterisk/sip_cisco_parking.conf`.

Alleen een lampje zonder parkeerfunctie kan ook: gebruik dan `featureID` `21`
(BLF Speed Dial) zonder `featureOptionMask`, met `<speedDialNumber>` en
`<contact>`. Met mask `1` belt de toets `x-cisco-serviceuri-blfpickup-701`
om een overgaand gesprek over te nemen; dat werkt niet voor
parkeerplaatsen.

Herstart het toestel zodat het de nieuwe bestanden van de TFTP-server
ophaalt.

### 4.3 Gebruik

- **Parkeren op een vaste plaats**: druk tijdens het gesprek op een groene
  (vrije) toets, bv. **Park 701**. Het gesprek staat op 701 en het lampje
  wordt rood.
- **Parkeren op een vrije plaats**: druk tijdens het gesprek op de softkey
  **Park** (eventueel via **Meer**) of op de Park-lijntoets. Het scherm toont
  de plaats, bv. 703.
- **Ophalen**: druk op de rode toets van de plaats, of bel het nummer.
- **Zonder toetsen**: **Transfer**, `700`, plaats beluisteren, **Transfer**.

## 5. Problemen oplossen

| Probleem | Oorzaak en oplossing |
|---|---|
| `700` geeft "nummer onbekend" | De context van het toestel bevat `include => parking-intern` niet, of `res_parking` is niet geladen (`module show like parking`) |
| BLF-lampjes blijven uit of knipperen | Controleer `pjsip show subscriptions inbound` (Yealink) of `sip show subscriptions` (Cisco). De hint moet te vinden zijn in de context van het toestel, voor Cisco de `subscribecontext` (`dialplan show 701@intern`), en `parkinghints = yes` moet aan staan |
| Cisco: parkeertoets verbindt door naar "nummer onbekend" | De extensies `_[x]-cisco-serviceuri-.` en `_dpark-.` zijn niet te vinden vanuit de context van de peer: controleer `include => parking-intern` en `dialplan show dpark-701@intern` |
| Cisco: lampje van een parkeertoets blijft uit | De regel `subscribe = 701@parkedcalls,…` ontbreekt bij de peer. Controleer met `sip show peer 201` of de abonnementen er staan; ze worden pas actief als het toestel geregistreerd is |
| Cisco: Park-softkey ontbreekt | `softKeyFile` niet ingesteld, `SoftKeys.xml` niet op de TFTP-server, of het toestel is niet herstart |
| Cisco: Park doet niets of geeft een fout | `cisco = yes` (of `cisco_usecallmanager = yes`) ontbreekt bij de peer, of de parking lot uit `park`/`parkinglot` bestaat niet. Kijk met `sip set debug peer 201` of er een REFER met `Park` binnenkomt |
| `#72` doet niets | DTMF niet via RFC 2833/AVT, of de opties `k`/`K` ontbreken in `Dial()`. Test met `core set verbose 5` en kijk of Asterisk de DTMF ziet |
| Ophalen geeft stilte of ophangen | De plaats is leeg (al opgehaald of teruggestuurd na de parkeertijd) |
| XML app toont geen bezetting | Test AMI met `telnet 127.0.0.1 5038`; controleer gebruiker, wachtwoord, `permit` en `write = call` in `manager.conf` |
| Gesprek komt na 2 minuten terug | Zo bedoeld (`parkingtime = 120`); pas aan in `res_parking.conf` |
