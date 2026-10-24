<?php
/**
 * Start de ingebouwde PHP-server, roept alle endpoints aan en controleert
 * of de antwoorden geldige Yealink XML zijn.
 *
 * Gebruik: php tests/run.php
 */
$root = dirname(__DIR__);
$port = 8765;
$server = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $root],
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $pipes
);
register_shutdown_function(fn() => proc_terminate($server));

for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
    usleep(100000);
}

$cases = [
    'index.php' => 'YealinkIPPhoneTextMenu',
    'phonebook.php' => 'YealinkIPPhoneTextMenu',
    'phonebook.php?q=anna' => 'YealinkIPPhoneTextMenu',
    'phonebook.php?q=302' => 'YealinkIPPhoneTextMenu',
    'phonebook.php?group=Support' => 'YealinkIPPhoneTextMenu',
    'phonebook.php?q=bestaatniet' => 'YealinkIPPhoneTextScreen',
    'contact.php?id=0' => 'YealinkIPPhoneDirectory',
    'contact.php?id=999' => 'YealinkIPPhoneTextScreen',
    'search.php' => 'YealinkIPPhoneInputScreen',
    'groups.php' => 'YealinkIPPhoneTextMenu',
    'info.php' => 'YealinkIPPhoneTextScreen',
    'remote-phonebook.php' => 'YealinkIPPhoneBook',
    'parking.php' => 'YealinkIPPhoneDirectory',
    'parking.php?help=1' => 'YealinkIPPhoneTextScreen',
];

$failures = 0;
$context = stream_context_create(['http' => [
    'header' => "User-Agent: Yealink SIP-T48S 66.86.0.15 80:5e:c0:12:34:56\r\n",
]]);
foreach ($cases as $path => $root) {
    $body = @file_get_contents("http://127.0.0.1:$port/$path", false, $context);
    $doc = new DOMDocument();
    $ok = $body !== false && @$doc->loadXML($body) && $doc->documentElement->nodeName === $root;
    if ($ok && $root !== 'YealinkIPPhoneBook') {
        $ok = $doc->getElementsByTagName('SoftKey')->length <= 6;
    }
    printf("%s %s\n", $ok ? 'OK  ' : 'FAIL', $path);
    if (!$ok) {
        $failures++;
        echo $body, "\n";
    }
}

// Paginering: met page_size 20 en 8 contacten is er precies één pagina.
$body = file_get_contents("http://127.0.0.1:$port/phonebook.php", false, $context);
if (str_contains($body, 'Volgende')) {
    echo "FAIL paginering toont onterecht 'Volgende'\n";
    $failures++;
}

// Parkeerplaatsen: standaard 701 t/m 710, ophalen door de plaats te bellen.
$body = file_get_contents("http://127.0.0.1:$port/parking.php", false, $context);
if (!str_contains($body, '<URI>701</URI>') || !str_contains($body, '<URI>710</URI>')
    || str_contains($body, '<URI>711</URI>')) {
    echo "FAIL parkeerplaatsen 701-710 niet correct\n";
    $failures++;
}

// Bezetting uit status_url: object- en lijstformaat, en onleesbare bron.
require_once dirname(__DIR__) . '/lib/Parking.php';
$tmp = tempnam(sys_get_temp_dir(), 'park');
$statusCases = [
    ['{"701": "Jan Jansen", "704": "+31201234567"}', ['701' => 'Jan Jansen', '704' => '+31201234567']],
    ['["702", 705]', ['702' => '', '705' => '']],
    ['{}', []],
    ['geen json', null],
];
foreach ($statusCases as [$json, $expected]) {
    file_put_contents($tmp, $json);
    $actual = parking_status($tmp);
    $actual = $actual === null ? null : array_combine(array_map('strval', array_keys($actual)), $actual);
    if ($actual !== $expected) {
        echo "FAIL parking_status($json): ", var_export($actual, true), "\n";
        $failures++;
    }
}
unlink($tmp);
if (parking_status('') !== null || parking_status('/bestaat/niet.json') !== null) {
    echo "FAIL parking_status zonder bron moet null geven\n";
    $failures++;
}

echo $failures ? "\n$failures test(s) mislukt\n" : "\nAlle tests geslaagd\n";
exit($failures ? 1 : 0);
