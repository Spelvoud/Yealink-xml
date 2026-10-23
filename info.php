<?php
/**
 * Informatiescherm met datum, tijd en gegevens van de telefoon.
 */
require_once __DIR__ . '/lib/Yealink.php';
require_once __DIR__ . '/lib/Contacts.php';

$config = yl_config();
$now = new DateTimeImmutable('now', new DateTimeZone($config['timezone']));

$days = ['zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag'];
$months = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli',
    'augustus', 'september', 'oktober', 'november', 'december'];

// Yealink-toestellen sturen o.a. model, firmware en MAC mee in de User-Agent,
// bv. "Yealink SIP-T48S 66.86.0.15 80:5e:c0:12:34:56".
$userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
$model = preg_match('/(SIP-?T\w+|T\d+\w*)/i', $userAgent, $m) ? $m[1] : 'onbekend';
$firmware = preg_match('/\b(\d+\.\d+\.\d+\.\d+)\b/', $userAgent, $m) ? $m[1] : 'onbekend';

$lines = [
    sprintf('Datum: %s %d %s %s', $days[(int) $now->format('w')], (int) $now->format('j'),
        $months[(int) $now->format('n') - 1], $now->format('Y')),
    'Tijd: ' . $now->format('H:i'),
    'Week: ' . (int) $now->format('W'),
    '',
    'Toestel: ' . $model,
    'Firmware: ' . $firmware,
    'IP-adres: ' . ($_SERVER['REMOTE_ADDR'] ?? 'onbekend'),
    '',
    'Contacten: ' . count(contacts_all()),
];

yl_send(yl_text_screen($config['app_name'] . ' - Informatie', implode("\n", $lines), [
    ['Vernieuw', yl_url('info.php')],
    ['Menu', yl_url('index.php')],
    ['Afsluiten', 'SoftKey:Exit'],
]));
