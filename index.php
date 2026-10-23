<?php
/**
 * Hoofdmenu van de Yealink T48 XML app.
 * Koppel deze URL aan een DSS-toets van het type "XML Browser".
 */
require_once __DIR__ . '/lib/Yealink.php';

$config = yl_config();

yl_send(yl_text_menu(
    $config['app_name'],
    [
        ['Telefoonboek', yl_url('phonebook.php')],
        ['Zoek contact', yl_url('search.php')],
        ['Afdelingen', yl_url('groups.php')],
        ['Informatie', yl_url('info.php')],
    ],
    [
        ['Kies', 'SoftKey:Select'],
        ['Afsluiten', 'SoftKey:Exit'],
    ]
));
