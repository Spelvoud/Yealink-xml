<?php
/**
 * Configuratie van de Yealink T48 XML app.
 */
return [
    // Naam die bovenaan het hoofdmenu staat.
    'app_name' => 'Bedrijfsapp',

    // Vaste basis-URL (bv. 'http://192.168.1.10/yealink'). Leeg = automatisch bepalen.
    'base_url' => '',

    // Aantal seconden waarna de telefoon het XML-scherm sluit (0 = nooit).
    'timeout' => 60,

    // Aantal contacten per pagina in het telefoonboek.
    'page_size' => 20,

    // Locatie van het contactenbestand.
    'contacts_file' => __DIR__ . '/data/contacts.json',

    // Tijdzone voor het informatiescherm.
    'timezone' => 'Europe/Amsterdam',

    // Parkeerplaatsen (call park) van de telefooncentrale.
    'parking' => [
        // Eerste parkeerplaats en het aantal plaatsen (bv. 701 t/m 710).
        'first_slot' => 701,
        'slot_count' => 10,

        // Nummer waarnaar je een gesprek doorverbindt om het te parkeren.
        'park_number' => '700',

        // Voorvoegsel om een geparkeerd gesprek op te halen, bv. '*88'.
        // Leeg = het nummer van de parkeerplaats zelf bellen (bv. FreePBX).
        'pickup_prefix' => '',

        // Optioneel: URL of bestand met de bezetting in JSON, bv.
        // {"701": "Jan Jansen (0612345678)", "704": "+31201234567"}.
        // Leeg = geen bezetting tonen.
        'status_url' => '',
    ],
];
