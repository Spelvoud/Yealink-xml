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
];
