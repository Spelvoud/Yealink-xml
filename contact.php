<?php
/**
 * Detail van één contact. Elk nummer wordt getoond als Directory-item,
 * zodat selecteren (of de toets "Bel") het nummer direct kiest.
 */
require_once __DIR__ . '/lib/Yealink.php';
require_once __DIR__ . '/lib/Contacts.php';

$contact = contacts_get((int) ($_GET['id'] ?? -1));

if ($contact === null) {
    yl_send(yl_text_screen(
        'Niet gevonden',
        'Dit contact bestaat niet (meer).',
        [
            ['Telefoonboek', yl_url('phonebook.php')],
            ['Afsluiten', 'SoftKey:Exit'],
        ]
    ));
    return;
}

$items = [];
foreach ($contact['numbers'] as $label => $number) {
    $items[] = [ucfirst($label) . ': ' . $number, preg_replace('/[^0-9+*#]/', '', $number)];
}

yl_send(yl_directory(
    $contact['name'] . ' - ' . $contact['group'],
    $items,
    [
        ['Bel', 'SoftKey:Dial'],
        ['Terug', yl_url('phonebook.php')],
        ['Menu', yl_url('index.php')],
        ['Afsluiten', 'SoftKey:Exit'],
    ]
));
