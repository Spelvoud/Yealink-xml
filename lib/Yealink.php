<?php
/**
 * Kleine helpers om Yealink XML Browser objecten op te bouwen.
 *
 * Ondersteunde objecten:
 *  - YealinkIPPhoneTextMenu
 *  - YealinkIPPhoneTextScreen
 *  - YealinkIPPhoneInputScreen
 *  - YealinkIPPhoneDirectory
 *  - YealinkIPPhoneBook (remote phonebook)
 */

function yl_config(): array
{
    static $config = null;
    return $config ??= require __DIR__ . '/../config.php';
}

function yl_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** Basis-URL van de app, bv. http://192.168.1.10/yealink */
function yl_base_url(): string
{
    $config = yl_config();
    if (!empty($config['base_url'])) {
        return rtrim($config['base_url'], '/');
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

function yl_url(string $script, array $params = []): string
{
    $url = yl_base_url() . '/' . ltrim($script, '/');
    if ($params) {
        $url .= '?' . http_build_query($params);
    }
    return $url;
}

/** Stuurt XML naar de telefoon en stopt het script. */
function yl_send(string $xml): void
{
    if (!headers_sent()) {
        header('Content-Type: text/xml; charset=UTF-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
    }
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $xml;
}

/** Gemeenschappelijke attributen (Beep, Timeout, LockIn). */
function yl_common_attrs(array $opts): string
{
    $config = yl_config();
    return sprintf(
        'Beep="%s" Timeout="%d" LockIn="no"',
        !empty($opts['beep']) ? 'yes' : 'no',
        $opts['timeout'] ?? $config['timeout']
    );
}

/**
 * @param array<int, array{0:string,1:string}> $softkeys lijst van [label, uri]
 */
function yl_softkeys(array $softkeys): string
{
    $xml = '';
    foreach (array_values($softkeys) as $i => [$label, $uri]) {
        $xml .= sprintf(
            "  <SoftKey index=\"%d\">\n    <Label>%s</Label>\n    <URI>%s</URI>\n  </SoftKey>\n",
            $i + 1,
            yl_escape($label),
            yl_escape($uri)
        );
    }
    return $xml;
}

/**
 * @param array<int, array{0:string,1:string}> $items lijst van [prompt, uri]
 */
function yl_text_menu(string $title, array $items, array $softkeys = [], array $opts = []): string
{
    $xml = sprintf(
        "<YealinkIPPhoneTextMenu style=\"%s\" defaultIndex=\"%d\" %s>\n  <Title wrap=\"no\">%s</Title>\n",
        $opts['style'] ?? 'numbered',
        $opts['defaultIndex'] ?? 1,
        yl_common_attrs($opts),
        yl_escape($title)
    );
    foreach ($items as [$prompt, $uri]) {
        $xml .= sprintf(
            "  <MenuItem>\n    <Prompt>%s</Prompt>\n    <URI>%s</URI>\n  </MenuItem>\n",
            yl_escape($prompt),
            yl_escape($uri)
        );
    }
    return $xml . yl_softkeys($softkeys) . "</YealinkIPPhoneTextMenu>\n";
}

function yl_text_screen(string $title, string $text, array $softkeys = [], array $opts = []): string
{
    return sprintf(
        "<YealinkIPPhoneTextScreen destroyOnExit=\"yes\" %s>\n  <Title wrap=\"yes\">%s</Title>\n  <Text>%s</Text>\n%s</YealinkIPPhoneTextScreen>\n",
        yl_common_attrs($opts),
        yl_escape($title),
        yl_escape($text),
        yl_softkeys($softkeys)
    );
}

/**
 * Invoerscherm met één tekstveld. De telefoon roept $url aan met ?$param=<invoer>.
 */
function yl_input_screen(string $title, string $url, string $prompt, string $param, string $default = '', array $softkeys = [], array $opts = []): string
{
    return sprintf(
        "<YealinkIPPhoneInputScreen type=\"string\" displayMode=\"normal\" inputLanguage=\"English\" %s>\n"
        . "  <Title>%s</Title>\n"
        . "  <URL>%s</URL>\n"
        . "  <InputField type=\"string\" password=\"no\" editable=\"yes\">\n"
        . "    <Prompt>%s</Prompt>\n"
        . "    <Parameter>%s</Parameter>\n"
        . "    <Selection>1</Selection>\n"
        . "    <Default>%s</Default>\n"
        . "  </InputField>\n"
        . "%s</YealinkIPPhoneInputScreen>\n",
        yl_common_attrs($opts),
        yl_escape($title),
        yl_escape($url),
        yl_escape($prompt),
        yl_escape($param),
        yl_escape($default),
        yl_softkeys($softkeys)
    );
}

/**
 * Directory: elk item is een nummer dat direct gekozen wordt bij selectie.
 *
 * @param array<int, array{0:string,1:string}> $items lijst van [naam, nummer]
 */
function yl_directory(string $title, array $items, array $softkeys = [], array $opts = []): string
{
    $xml = sprintf(
        "<YealinkIPPhoneDirectory style=\"none\" defaultIndex=\"%d\" %s>\n  <Title>%s</Title>\n",
        $opts['defaultIndex'] ?? 1,
        yl_common_attrs($opts),
        yl_escape($title)
    );
    foreach ($items as [$prompt, $number]) {
        $xml .= sprintf(
            "  <MenuItem>\n    <Prompt>%s</Prompt>\n    <URI>%s</URI>\n  </MenuItem>\n",
            yl_escape($prompt),
            yl_escape($number)
        );
    }
    return $xml . yl_softkeys($softkeys) . "</YealinkIPPhoneDirectory>\n";
}
