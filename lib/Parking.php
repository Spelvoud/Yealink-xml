<?php
/**
 * Bezetting van de parkeerplaatsen (call park).
 */

/**
 * Leest de bezetting uit status_url. Geeft null als die niet is ingesteld of
 * niet leesbaar is, anders een lijst van parkeerplaats => omschrijving.
 *
 * De bron is een URL of bestand met JSON, of de Asterisk Manager Interface:
 * ami://gebruiker:wachtwoord@host:5038/parkeerlot
 *
 * @return array<string, string>|null
 */
function parking_status(string $source): ?array
{
    if ($source === '') {
        return null;
    }
    if (str_starts_with($source, 'ami://')) {
        $raw = parking_ami_query($source);
        return $raw === null ? null : parking_parse_ami($raw);
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

/**
 * Vraagt via AMI de geparkeerde gesprekken op (actie ParkedCalls).
 * Geeft het ruwe antwoord, of null als verbinden of inloggen mislukt.
 */
function parking_ami_query(string $url, float $timeout = 2.0): ?string
{
    $parts = parse_url($url);
    if (!$parts || empty($parts['host']) || !isset($parts['user'])) {
        return null;
    }
    $lot = trim(rawurldecode($parts['path'] ?? ''), '/');
    $socket = @stream_socket_client(
        sprintf('tcp://%s:%d', $parts['host'], $parts['port'] ?? 5038),
        $errno, $errstr, $timeout
    );
    if ($socket === false) {
        return null;
    }
    stream_set_timeout($socket, (int) ceil($timeout));

    $action = "Action: ParkedCalls\r\nActionID: yealink-parking\r\n";
    if ($lot !== '') {
        $action .= "ParkingLot: $lot\r\n";
    }
    fwrite($socket, sprintf(
        "Action: Login\r\nUsername: %s\r\nSecret: %s\r\nEvents: off\r\n\r\n%s\r\nAction: Logoff\r\n\r\n",
        rawurldecode($parts['user']),
        rawurldecode($parts['pass'] ?? ''),
        $action
    ));

    $raw = '';
    $deadline = microtime(true) + $timeout;
    while (!feof($socket) && microtime(true) < $deadline) {
        $line = fgets($socket);
        if ($line === false) {
            break;
        }
        $raw .= $line;
        if (str_contains($raw, 'Event: ParkedCallsComplete')) {
            break;
        }
    }
    fclose($socket);

    $loggedIn = preg_match('/^Response: Success/mi', $raw)
        && !preg_match('/^Message: Authentication failed/mi', $raw);
    return $loggedIn && str_contains($raw, 'ParkedCallsComplete') ? $raw : null;
}

/**
 * Zet een AMI-antwoord met ParkedCall-events om in plaats => "Naam (nummer)".
 *
 * @return array<string, string>
 */
function parking_parse_ami(string $raw): array
{
    $status = [];
    foreach (preg_split('/\r?\n\r?\n/', $raw) as $block) {
        $fields = [];
        foreach (preg_split('/\r?\n/', trim($block)) as $line) {
            [$key, $value] = array_pad(explode(':', $line, 2), 2, '');
            $fields[trim($key)] = trim($value);
        }
        if (($fields['Event'] ?? '') !== 'ParkedCall' || ($fields['ParkingSpace'] ?? '') === '') {
            continue;
        }
        $clean = fn(string $v) => in_array($v, ['', '<unknown>'], true) ? '' : $v;
        $name = $clean($fields['ParkeeCallerIDName'] ?? '');
        $number = $clean($fields['ParkeeCallerIDNum'] ?? '');
        $status[$fields['ParkingSpace']] = match (true) {
            $name !== '' && $number !== '' && $name !== $number => "$name ($number)",
            default => $name !== '' ? $name : $number,
        };
    }
    return $status;
}
