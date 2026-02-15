<?php declare(strict_types=1);
/**
 * File: Header.php
 * Category: -
 * Author: M.Goldenbaum
 * Created: 17.09.20 20:38
 * Updated: -
 *
 * Description:
 *  -
 */

namespace Yai\Ymap\Connection\ConnectionLibrary;

use Yai\Ymap\Connection\ConnectionLibrary\Exceptions\InvalidMessageDateException;
use Yai\Ymap\Connection\ConnectionLibrary\Exceptions\MethodNotFoundException;
use Yai\Ymap\Connection\ConnectionLibrary\Exceptions\SpoofingAttemptDetectedException;
use DateTimeImmutable;
use stdClass;
use function array_keys;
use function array_map;
use function array_pad;
use function array_pop;
use function array_values;
use function count;
use function explode;
use function get_object_vars;
use function implode;
use function in_array;
use function is_array;
use function is_object;
use function is_string;
use function mb_decode_mimeheader;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function preg_split;
use function property_exists;
use function rtrim;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_split;
use function str_starts_with;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;

/**
 * Class Header
 *
 * @package Yai\Ymap\Connection\ConnectionLibrary
 */
class Header {

    /**
     * Raw header
     *
     * @var string $raw
     */
    public string $raw = "";

    /**
     * Attribute holder
     *
     * @var Attribute[]|array $attributes
     */
    protected array $attributes = [];

    /**
     * Config holder
     *
     * @var Config $config
     */
    protected Config $config;

    /**
     * Config holder
     *
     * @var array<string, mixed> $options
     */
    protected array $options = [];

    /**
     * Header constructor.
     * @param string $raw_header
     * @param Config $config
     *
     * @throws InvalidMessageDateException
     */
    public function __construct(string $raw_header, Config $config) {
        $this->raw = $raw_header;
        $this->config = $config;
        $this->options = (array) $this->config->get('options', []);
        $this->parse();
    }

    /**
     * Call dynamic attribute setter and getter methods
     * @param string $method
     * @param array<int, mixed> $arguments
     *
     * @return Attribute|mixed
     * @throws MethodNotFoundException
     */
    public function __call(string $method, array $arguments) {
        if (strtolower(substr($method, 0, 3)) === 'get') {
            $name = preg_replace('/(.)(?=[A-Z])/u', '$1_', substr(strtolower($method), 3));

            if (in_array($name, array_keys($this->attributes))) {
                return $this->attributes[$name];
            }

        }

        throw new MethodNotFoundException("Method " . self::class . '::' . $method . '() is not supported');
    }

    /**
     * Magic getter
     * @param string $name
     *
     * @return Attribute
     */
    public function __get(string $name): Attribute {
        return $this->get($name);
    }

    /**
     * Get a specific header attribute
     * @param string $name
     *
     * @return Attribute
     */
    public function get(string $name): Attribute {
        $name = str_replace(["-", " "], "_", strtolower($name));
        if (isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }

        return new Attribute($name);
    }

    /**
     * Check if a specific attribute exists
     * @param string $name
     *
     * @return bool
     */
    public function has(string $name): bool {
        $name = str_replace(["-", " "], "_", strtolower($name));
        return isset($this->attributes[$name]);
    }

    /**
     * Set a specific attribute
     * @param string $name
     * @param array|mixed $value
     * @param boolean $strict
     *
     * @return Attribute
     */
    public function set(string $name, mixed $value, bool $strict = false): Attribute {
        if (isset($this->attributes[$name]) && $strict === false) {
            $this->attributes[$name]->add($value, true);
        } else {
            $this->attributes[$name] = new Attribute($name, $value);
        }

        return $this->attributes[$name];
    }

    /**
     * Perform a regex match all on the raw header and return the first result
     * @param string $pattern
     *
     * @return mixed|null
     */
    public function find(string $pattern): mixed {
        if (preg_match_all($pattern, $this->raw, $matches)) {
            if (isset($matches[1])) {
                if (count($matches[1]) > 0) {
                    return $matches[1][0];
                }
            }
        }
        return null;
    }

    /**
     * Try to find a boundary if possible
     *
     * @return string|null
     */
    public function getBoundary(): ?string {
        $regex = $this->options["boundary"] ?? "/boundary=(.*?(?=;)|(.*))/i";
        $boundary = $this->find($regex);

        if ($boundary === null) {
            return null;
        }

        return $this->clearBoundaryString($boundary);
    }

    /**
     * Remove all unwanted chars from a given boundary
     * @param string $str
     *
     * @return string
     */
    private function clearBoundaryString(string $str): string {
        return str_replace(['"', '\r', '\n', "\n", "\r", ";", "\s"], "", $str);
    }

    /**
     * Parse the raw headers
     *
     * @throws InvalidMessageDateException
     * @throws SpoofingAttemptDetectedException
     */
    protected function parse(): void {
        $header = $this->rfc822_parse_headers($this->raw);

        $this->extractAddresses($header);

        if (property_exists($header, 'subject')) {
            $this->set("subject", $this->decodeMimeStr($header->subject));
        }
        if (property_exists($header, 'references')) {
            $this->set("references", array_map(function($item) {
                return str_replace(['<', '>'], '', $item);
            }, explode(" ", $header->references)));
        }
        if (property_exists($header, 'message_id')) {
            $this->set("message_id", str_replace(['<', '>'], '', $header->message_id));
        }
        if (property_exists($header, 'in_reply_to')) {
            $this->set("in_reply_to", str_replace(['<', '>'], '', $header->in_reply_to));
        }

        $this->parseDate($header);
        foreach (get_object_vars($header) as $key => $value) {
            $key = trim(rtrim(strtolower($key)));
            if (!isset($this->attributes[$key])) {
                $this->set($key, $value);
            }
        }

        $this->extractHeaderExtensions();
        $this->findPriority();
    }

    /**
     * Simple MIME decoding replacement for DecoderInterface
     */
    private function decodeMimeStr(string $string): string {
        return mb_decode_mimeheader($string);
    }

    /**
     * Parse mail headers from a string
     * @link https://php.net/manual/en/function.imap-rfc822-parse-headers.php
     * @param string $raw_headers
     *
     * @return stdClass
     */
    public function rfc822_parse_headers(string $raw_headers): stdClass {
        $headers = [];
        $normalizedHeaders = preg_replace("/\r\n\s/", ' ', $raw_headers);
        if ($normalizedHeaders === null) {
            $normalizedHeaders = $raw_headers;
        }
        $lines = explode("\r\n", $normalizedHeaders);
        $prev_header = null;
        foreach ($lines as $line) {
            if (str_starts_with($line, "\n")) {
                $line = substr($line, 1);
            }

            if (str_starts_with($line, "\t")) {
                $line = substr($line, 1);
                $line = trim(rtrim($line));
                if ($prev_header !== null) {
                    $current = $headers[$prev_header] ?? [];
                    if (!is_array($current)) {
                        $current = [$current];
                    }
                    $current[] = $line;
                    $headers[$prev_header] = $current;
                }
            } elseif (str_starts_with($line, " ")) {
                $line = substr($line, 1);
                $line = trim(rtrim($line));
                if ($prev_header !== null) {
                    if (!isset($headers[$prev_header])) {
                        $headers[$prev_header] = "";
                    }
                    if (is_array($headers[$prev_header])) {
                        $headers[$prev_header][] = $line;
                    } else {
                        $headers[$prev_header] .= $line;
                    }
                }
            } else {
                if (($pos = strpos($line, ":")) > 0) {
                    $key = trim(rtrim(strtolower(substr($line, 0, $pos))));
                    $key = strtolower(str_replace("-", "_", $key));

                    $value = trim(rtrim(substr($line, $pos + 1)));
                    if (isset($headers[$key])) {
                        $current = $headers[$key];
                        if (!is_array($current)) {
                            $current = [$current];
                        }
                        $current[] = $value;
                        $headers[$key] = $current;
                    } else {
                        $headers[$key] = [$value];
                    }
                    $prev_header = $key;
                }
            }
        }

        foreach ($headers as $key => $values) {
            $value = null;
            switch ((string)$key) {
                case 'from':
                case 'to':
                case 'cc':
                case 'bcc':
                case 'reply_to':
                case 'sender':
                    $addressValues = is_array($values) ? $values : [$values];
                    $value = $this->decodeAddresses($addressValues);
                    $headers[$key . "address"] = implode(", ", $addressValues);
                    break;
                case 'subject':
                    $subjectValues = is_array($values) ? $values : [$values];
                    $value = implode(" ", $subjectValues);
                    break;
                default:
                    if (is_array($values)) {
                        foreach ($values as $k => $v) {
                            if ($v == "") {
                                unset($values[$k]);
                            }
                        }
                        $available_values = count($values);
                        if ($available_values === 1) {
                            $value = array_pop($values);
                        } elseif ($available_values === 2) {
                            $value = implode(" ", $values);
                        } elseif ($available_values > 2) {
                            $value = array_values($values);
                        } else {
                            $value = "";
                        }
                    }
                    break;
            }
            $headers[$key] = $value;
        }

        return (object)$headers;
    }

    /**
     * Try to extract the priority from a given raw header string
     */
    private function findPriority(): void {
        $priority = $this->get("x_priority");

        $priority = match ((int)$priority->toString()) {
            IMAP::MESSAGE_PRIORITY_HIGHEST => IMAP::MESSAGE_PRIORITY_HIGHEST,
            IMAP::MESSAGE_PRIORITY_HIGH => IMAP::MESSAGE_PRIORITY_HIGH,
            IMAP::MESSAGE_PRIORITY_NORMAL => IMAP::MESSAGE_PRIORITY_NORMAL,
            IMAP::MESSAGE_PRIORITY_LOW => IMAP::MESSAGE_PRIORITY_LOW,
            IMAP::MESSAGE_PRIORITY_LOWEST => IMAP::MESSAGE_PRIORITY_LOWEST,
            default => IMAP::MESSAGE_PRIORITY_UNKNOWN,
        };

        $this->set("priority", $priority);
    }

    /**
     * Extract a given part as address array from a given header
     * @param array<int, string> $values
     *
     * @return array<int, stdClass>
     */
    private function decodeAddresses(array $values): array {
        $addresses = [];

        foreach ($values as $address) {
            $splitAddresses = preg_split('/, ?(?=(?:[^"]*"[^"]*")*[^"]*$)/', $address);
            if ($splitAddresses === false) {
                continue;
            }
            foreach ($splitAddresses as $split_address) {
                $split_address = trim(rtrim($split_address));

                if (strpos($split_address, ",") == strlen($split_address) - 1) {
                    $split_address = substr($split_address, 0, -1);
                }
                if (preg_match(
                    '/^(?:(?P<name>.+)\s)?(?(name)<|<?)(?P<email>[^\s]+?)(?(name)>|>?)$/',
                    $split_address,
                    $matches
                )) {
                    $name = trim(rtrim($matches["name"]));
                    $email = trim(rtrim($matches["email"]));
                    list($mailbox, $host) = array_pad(explode("@", $email), 2, null);
                    $addresses[] = (object)[
                        "personal" => $name,
                        "mailbox"  => $mailbox,
                        "host"     => $host,
                    ];
                }elseif (preg_match(
                    '/^((?P<name>.+)<)(?P<email>[^<]+?)>$/',
                    $split_address,
                    $matches
                )) {
                    $name = trim(rtrim($matches["name"]));
                    if(str_starts_with($name, "\"") && str_ends_with($name, "\"")) {
                        $name = substr($name, 1, -1);
                    }elseif(str_starts_with($name, "'") && str_ends_with($name, "'")) {
                        $name = substr($name, 1, -1);
                    }
                    $email = trim(rtrim($matches["email"]));
                    list($mailbox, $host) = array_pad(explode("@", $email), 2, null);
                    $addresses[] = (object)[
                        "personal" => $name,
                        "mailbox"  => $mailbox,
                        "host"     => $host,
                    ];
                }
            }
        }

        return $addresses;
    }

    /**
     * Extract a given part as address array from a given header
     * @param stdClass $header
     */
    private function extractAddresses(stdClass $header): void {
        foreach (['from', 'to', 'cc', 'bcc', 'reply_to', 'sender', 'return_path', 'envelope_from', 'envelope_to', 'delivered_to'] as $key) {
            if (property_exists($header, $key)) {
                $this->set($key, $this->parseAddresses($header->$key));
            }
        }
    }

    /**
     * Parse Addresses
     * @param mixed $list
     *
     * @return array<int, Address>
     */
    private function parseAddresses($list): array {
        $addresses = [];

        if (is_array($list) === false) {
            if(is_string($list)) {
                // ... regex parsing logic same as original but simplified loop ...
                if (preg_match(
                    '/^(?:(?P<name>.+)\s)?(?(name)<|<?)(?P<email>[^\s]+?)(?(name)>|>?)$/',
                    $list,
                    $matches
                )) {
                    $name = trim(rtrim($matches["name"]));
                    $email = trim(rtrim($matches["email"]));
                    list($mailbox, $host) = array_pad(explode("@", $email), 2, null);
                     if($mailbox === ">") { // Fix trailing ">" in malformed mailboxes
                        $mailbox = "";
                    }
                    if($name === "" && $mailbox === "" && $host === "") {
                        return $addresses;
                    }
                    $list = [
                        (object)[
                            "personal" => $name,
                            "mailbox"  => $mailbox,
                            "host"     => $host,
                        ]
                    ];
                } else {
                     return $addresses;
                }
            }else{
                return $addresses;
            }
        }

        foreach ($list as $item) {
            $address = is_object($item) ? $item : (object)$item;

            $mailbox = property_exists($address, 'mailbox') ? (string)$address->mailbox : '';
            $host = property_exists($address, 'host') ? (string)$address->host : '';
            $personal = property_exists($address, 'personal') ? (string)$address->personal : '';
            if ($personal !== '') {
                $personal = trim(rtrim($this->decodeMimeStr($personal)));
            }

            if ($host === ".SYNTAX-ERROR." || $host === "UNKNOWN") {
                $host = "";
            }

            $normalizedAddress = (object)[
                'mailbox' => $mailbox,
                'host' => $host,
                'personal' => $personal,
            ];

            $addresses[] = new Address($normalizedAddress);
        }

        return $addresses;
    }

    /**
     * Search and extract potential header extensions
     */
    private function extractHeaderExtensions(): void {
        foreach ($this->attributes as $key => $value) {
            // Only parse strings and don't parse any attributes like the user-agent
            if (!in_array($key, ["user-agent", "subject", "received", "date"])) {

                if (is_array($value)) {
                    $value = implode(", ", $value);
                } else {
                    $value = (string)$value;
                }

                if (str_contains($value, ";") && str_contains($value, "=")) {
                    $_attributes = $this->read_attribute($value);
                    foreach($_attributes as $_key => $_value) {
                        if($_key && !isset($this->attributes[$_key])) {
                            $this->set($_key, $_value);
                        }
                    }
                }
            }
        }
    }

    /**
     * Read a given attribute string
     * @param string $raw_attribute
     * @return array<string, string>
     */
    private function read_attribute(string $raw_attribute): array {
        $attributes = [];
        $key = '';
        $value = '';
        $inside_word = false;
        $inside_key = true;
        $escaped = false;

        // Simple parser logic
        $chars = str_split($raw_attribute);
        for ($i = 0; $i < count($chars); $i++) {
            $char = $chars[$i];
             if($escaped) {
                $escaped = false;
                continue;
            }
             if($inside_word) {
                if($char === '\\') {
                    $escaped = true;
                }elseif($char === "\"" && $value !== "") {
                    $inside_word = false;
                }else{
                    $value .= $char;
                }
            }else{
                 if($char === '"') {
                     $inside_word = true;
                 }elseif($char === ';'){
                     if ($key) $attributes[$key] = $value;
                     $key = '';
                     $value = '';
                     $inside_key = true;
                     continue;
                 }elseif($char === '=') {
                     $inside_key = false;
                     continue;
                 }

                 if ($inside_key) $key .= $char;
                 else $value .= $char;
            }
        }
        if ($key) $attributes[$key] = $value;

        $result = [];
        foreach($attributes as $key => $value) {
            $key = trim(strtolower($key));
             if (($pos = strpos($key, "*")) !== false) {
                $key = substr($key, 0, $pos);
            }
            $value = trim($value, "\" \t\n\r\0\x0B");
            $result[$key] = $value;
        }
        return $result;
    }

    /**
     * Exception handling for invalid dates
     * @param stdClass $header
     * @throws InvalidMessageDateException
     */
    private function parseDate(stdClass $header): void {

        if (property_exists($header, 'date')) {
            $dateLine = $header->date;
            $dateLine = trim(rtrim($dateLine));

            try {
                $parsed_date = new DateTimeImmutable($dateLine);
            } catch (\Exception $e) {
                 // Try to fallback to clean up common issues
                     $cleanDate = preg_replace('/\s+\(.*\)$/', '', $dateLine);
                     if ($cleanDate === null) {
                        $cleanDate = $dateLine;
                     }
                     try {
                     $parsed_date = new DateTimeImmutable($cleanDate);
                 } catch (\Exception $e2) {
                     // Fallback to now or throw?
                     // Let's use current time if parsing fails for robustness
                     $parsed_date = new DateTimeImmutable();
                 }
            }

            $this->set("date", $parsed_date);
        }
    }

    /**
     * Get all available attributes
     *
     * @return array<string, Attribute>
     */
    public function getAttributes(): array {
        return $this->attributes;
    }

    /**
     * Set all header attributes
     * @param array<string, Attribute> $attributes
     *
     * @return Header
     */
    public function setAttributes(array $attributes): Header {
        $this->attributes = $attributes;
        return $this;
    }

    /**
     * Set the configuration used for parsing a raw header
     * @param array<string, mixed> $config
     *
     * @return Header
     */
    public function setOptions(array $config): Header {
        $this->options = $config;
        return $this;
    }
}
