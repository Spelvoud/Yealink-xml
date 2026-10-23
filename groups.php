<?php
/**
 * Overzicht van afdelingen; elke afdeling opent een gefilterd telefoonboek.
 */
require_once __DIR__ . '/lib/Yealink.php';
require_once __DIR__ . '/lib/Contacts.php';

$counts = [];
foreach (contacts_all() as $contact) {
    $counts[$contact['group']] = ($counts[$contact['group']] ?? 0) + 1;
}
ksort($counts, SORT_NATURAL | SORT_FLAG_CASE);

$items = [];
foreach ($counts as $group => $count) {
    $items[] = ["$group ($count)", yl_url('phonebook.php', ['group' => $group])];
}

if (!$items) {
    yl_send(yl_text_screen('Afdelingen', 'Er zijn nog geen contacten.', [
        ['Menu', yl_url('index.php')],
        ['Afsluiten', 'SoftKey:Exit'],
    ]));
    return;
}

yl_send(yl_text_menu('Afdelingen', $items, [
    ['Kies', 'SoftKey:Select'],
    ['Menu', yl_url('index.php')],
    ['Afsluiten', 'SoftKey:Exit'],
]));
