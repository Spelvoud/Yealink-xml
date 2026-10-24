<?php
/**
 * Parking lot helper: toont de parkeerplaatsen van de centrale en haalt een
 * geparkeerd gesprek op door de parkeerplaats te bellen.
 *
 * Parameters:
 *   help  1 = uitleg over parkeren tonen
 */
require_once __DIR__ . '/lib/Yealink.php';
require_once __DIR__ . '/lib/Parking.php';

$parking = yl_config()['parking'];
$parkNumber = (string) $parking['park_number'];

if (!empty($_GET['help'])) {
    yl_send(yl_text_screen('Gesprek parkeren', implode("\n", [
        'Parkeren:',
        "1. Druk tijdens het gesprek op Transfer.",
        "2. Kies $parkNumber en druk op Transfer.",
        '3. De centrale noemt de parkeerplaats.',
        '',
        'Ophalen:',
        'Open Parkeerplaatsen, kies de plaats en druk op Ophalen.',
    ]), [
        ['Terug', yl_url('parking.php')],
        ['Menu', yl_url('index.php')],
        ['Afsluiten', 'SoftKey:Exit'],
    ]));
    return;
}

$status = parking_status((string) $parking['status_url']);

$items = [];
$first = (int) $parking['first_slot'];
for ($slot = $first; $slot < $first + max(1, (int) $parking['slot_count']); $slot++) {
    $label = "Plaats $slot";
    if ($status !== null) {
        $caller = $status[(string) $slot] ?? null;
        $label .= $caller === null ? ' - vrij' : ' - bezet' . ($caller !== '' ? ": $caller" : '');
    }
    $items[] = [$label, $parking['pickup_prefix'] . $slot];
}

$title = 'Parkeerplaatsen';
if ($status !== null) {
    $occupied = count(array_filter(array_keys($status),
        fn($slot) => $slot >= $first && $slot < $first + count($items)));
    $title .= " ($occupied bezet)";
}

yl_send(yl_directory($title, $items, [
    ['Ophalen', 'SoftKey:Dial'],
    ['Vernieuw', yl_url('parking.php')],
    ['Uitleg', yl_url('parking.php', ['help' => 1])],
    ['Menu', yl_url('index.php')],
    ['Afsluiten', 'SoftKey:Exit'],
]));
