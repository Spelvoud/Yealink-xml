<?php
/**
 * Bezetting van de parkeerplaatsen (call park).
 */

/**
 * Leest de bezetting uit status_url. Geeft null als die niet is ingesteld of
 * niet leesbaar is, anders een lijst van parkeerplaats => omschrijving.
 *
 * @return array<string, string>|null
 */
function parking_status(string $source): ?array
{
    if ($source === '') {
        return null;
    }
    $context = stream_context_create(['http' => ['timeout' => 2]]);
    $raw = @file_get_contents($source, false, $context);
    $data = $raw === false ? null : json_decode($raw, true);
    if (!is_array($data)) {
        return null;
    }
    // Zowel {"701": "Jan"} als ["701", "704"] wordt geaccepteerd.
    $isList = array_is_list($data);
    $status = [];
    foreach ($data as $slot => $caller) {
        if (!is_scalar($caller)) {
            continue;
        }
        if ($isList) {
            $status[(string) $caller] = '';
        } else {
            $status[(string) $slot] = (string) $caller;
        }
    }
    return $status;
}
