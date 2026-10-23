<?php
/**
 * Zoekscherm. Na "Zoek" roept de telefoon phonebook.php?q=<invoer> aan.
 */
require_once __DIR__ . '/lib/Yealink.php';

yl_send(yl_input_screen(
    'Zoek contact',
    yl_url('phonebook.php'),
    'Naam of nummer:',
    'q',
    (string) ($_GET['q'] ?? ''),
    [
        ['Zoek', 'SoftKey:Submit'],
        ['Wis', 'SoftKey:BackSpace'],
        ['abc/123', 'SoftKey:ChangeMode'],
        ['Annuleer', yl_url('index.php')],
    ]
));
