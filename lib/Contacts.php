<?php
require_once __DIR__ . '/Yealink.php';

/**
 * Toegang tot de contactenlijst (data/contacts.json).
 */

/** @return array<int, array{id:int, name:string, group:string, numbers:array<string,string>}> */
function contacts_all(): array
{
    $config = yl_config();
    $raw = @file_get_contents($config['contacts_file']);
    $data = $raw === false ? null : json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }

    $contacts = [];
    foreach ($data as $entry) {
        if (empty($entry['name']) || empty($entry['numbers']) || !is_array($entry['numbers'])) {
            continue;
        }
        $contacts[] = [
            'name' => (string) $entry['name'],
            'group' => (string) ($entry['group'] ?? 'Overig'),
            'numbers' => array_map('strval', $entry['numbers']),
        ];
    }
    usort($contacts, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    foreach ($contacts as $i => &$contact) {
        $contact['id'] = $i;
    }
    unset($contact);
    return $contacts;
}

/** Zoekt op (deel van) naam of nummer, hoofdletterongevoelig. */
function contacts_search(string $query): array
{
    $query = trim($query);
    if ($query === '') {
        return contacts_all();
    }
    $needle = mb_strtolower($query);
    $digits = preg_replace('/\D/', '', $query);

    return array_values(array_filter(contacts_all(), function ($c) use ($needle, $digits) {
        if (str_contains(mb_strtolower($c['name']), $needle)) {
            return true;
        }
        if ($digits !== '') {
            foreach ($c['numbers'] as $number) {
                if (str_contains(preg_replace('/\D/', '', $number), $digits)) {
                    return true;
                }
            }
        }
        return false;
    }));
}

/** Haalt één contact op via index in de gesorteerde lijst. */
function contacts_get(int $id): ?array
{
    return contacts_all()[$id] ?? null;
}
