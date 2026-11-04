<?php
/**
 * Minimale nep-AMI-server voor de tests: leest per verbinding Login en
 * ParkedCalls en stuurt twee geparkeerde gesprekken terug. Draait tot hij
 * gestopt wordt.
 *
 * Gebruik: php tests/fake-ami.php <poort> <wachtwoord>
 */
[, $port, $secret] = $argv;
$server = stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr);
while ($conn = @stream_socket_accept($server, 30)) {
    @fwrite($conn, "Asterisk Call Manager/9.0.0\r\n");

    $request = '';
    while (substr_count($request, "\r\n\r\n") < 2 && ($line = fgets($conn)) !== false) {
        $request .= $line;
    }
    if (!str_contains($request, "Secret: $secret\r\n")) {
        @fwrite($conn, "Response: Error\r\nMessage: Authentication failed\r\n\r\n");
        fclose($conn);
        continue;
    }
    @fwrite($conn, "Response: Success\r\nMessage: Authentication accepted\r\n\r\n"
        . "Response: Success\r\nActionID: yealink-parking\r\nEventList: start\r\nMessage: Parked calls will follow\r\n\r\n"
        . "Event: ParkedCall\r\nActionID: yealink-parking\r\nParkeeChannel: PJSIP/trunk-00000001\r\n"
        . "ParkeeCallerIDNum: 0612345678\r\nParkeeCallerIDName: Jan Jansen\r\nParkingLot: default\r\nParkingSpace: 701\r\n\r\n"
        . "Event: ParkedCall\r\nActionID: yealink-parking\r\nParkeeChannel: PJSIP/trunk-00000002\r\n"
        . "ParkeeCallerIDNum: +31201234567\r\nParkeeCallerIDName: <unknown>\r\nParkingLot: default\r\nParkingSpace: 704\r\n\r\n"
        . "Event: ParkedCallsComplete\r\nActionID: yealink-parking\r\nTotal: 2\r\n\r\n");
    fclose($conn);
}
