<?php
/**
 * Remote phonebook in het YealinkIPPhoneBook-formaat.
 * Instellen op de T48 via Directory > Remote Phone Book, of in de provisioning:
 *   remote_phonebook.data.1.url = http://server/yealink/remote-phonebook.php
 *   remote_phonebook.data.1.name = Bedrijf
 */
require_once __DIR__ . '/lib/Yealink.php';
require_once __DIR__ . '/lib/Contacts.php';

$config = yl_config();

$groups = [];
foreach (contacts_all() as $contact) {
    $groups[$contact['group']][] = $contact;
}
ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

$xml = "<YealinkIPPhoneBook>\n  <Title>" . yl_escape($config['app_name']) . "</Title>\n";
foreach ($groups as $group => $contacts) {
    $xml .= '  <Menu Name="' . yl_escape($group) . "\">\n";
    foreach ($contacts as $contact) {
        // Het formaat ondersteunt maximaal drie nummers per contact.
        $numbers = array_slice(array_values($contact['numbers']), 0, 3);
        $attrs = 'Name="' . yl_escape($contact['name']) . '"';
        foreach ($numbers as $i => $number) {
            $attrs .= sprintf(' Phone%d="%s"', $i + 1, yl_escape($number));
        }
        $xml .= "    <Unit $attrs default_photo=\"Resource:\"/>\n";
    }
    $xml .= "  </Menu>\n";
}
$xml .= "</YealinkIPPhoneBook>\n";

yl_send($xml);
