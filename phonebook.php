<?php
/**
 * Telefoonboek met paginering.
 *
 * Parameters:
 *   q     zoekterm (optioneel)
 *   group afdeling (optioneel)
 *   page  paginanummer, begint bij 1
 */
require_once __DIR__ . '/lib/Yealink.php';
require_once __DIR__ . '/lib/Contacts.php';

$config = yl_config();
$query = trim((string) ($_GET['q'] ?? ''));
$group = trim((string) ($_GET['group'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));

$contacts = contacts_search($query);
if ($group !== '') {
    $contacts = array_values(array_filter($contacts, fn($c) => $c['group'] === $group));
}

if (!$contacts) {
    yl_send(yl_text_screen(
        'Geen resultaten',
        $query !== '' ? "Geen contacten gevonden voor \"$query\"." : 'Het telefoonboek is leeg.',
        [
            ['Opnieuw', yl_url('search.php', ['q' => $query])],
            ['Menu', yl_url('index.php')],
            ['Afsluiten', 'SoftKey:Exit'],
        ]
    ));
    return;
}

$pageSize = max(1, (int) $config['page_size']);
$pages = (int) ceil(count($contacts) / $pageSize);
$page = min($page, $pages);
$slice = array_slice($contacts, ($page - 1) * $pageSize, $pageSize);

$items = [];
foreach ($slice as $contact) {
    $items[] = [$contact['name'], yl_url('contact.php', ['id' => $contact['id']])];
}

$title = $group !== '' ? $group : ($query !== '' ? "Zoeken: $query" : 'Telefoonboek');
if ($pages > 1) {
    $title .= " ($page/$pages)";
}

$params = array_filter(['q' => $query, 'group' => $group], 'strlen');
$softkeys = [['Kies', 'SoftKey:Select']];
if ($page > 1) {
    $softkeys[] = ['Vorige', yl_url('phonebook.php', $params + ['page' => $page - 1])];
}
if ($page < $pages) {
    $softkeys[] = ['Volgende', yl_url('phonebook.php', $params + ['page' => $page + 1])];
}
$softkeys[] = ['Zoek', yl_url('search.php')];
$softkeys[] = ['Menu', yl_url('index.php')];
$softkeys[] = ['Afsluiten', 'SoftKey:Exit'];

yl_send(yl_text_menu($title, $items, $softkeys, ['style' => 'none']));
