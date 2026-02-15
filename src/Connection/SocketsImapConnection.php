<?php declare(strict_types=1);

namespace Yai\Ymap\Connection;

use Yai\Ymap\Connection\ConnectionLibrary\IMAP;
use Yai\Ymap\Connection\ConnectionLibrary\Config;
use Yai\Ymap\Connection\ConnectionLibrary\ImapProtocol;
use Yai\Ymap\Exceptions\ConnectionException;
use Yai\Ymap\Connection\ConnectionLibrary\Header;
use stdClass;
use const PREG_SPLIT_DELIM_CAPTURE;
use const PREG_SPLIT_NO_EMPTY;
use function array_map;
use function base64_decode;
use function count;
use function file_put_contents;
use function fwrite;
use function iconv;
use function in_array;
use function is_array;
use function is_resource;
use function is_string;
use function json_encode;
use function preg_match;
use function preg_match_all;
use function preg_split;
use function quoted_printable_decode;
use function str_replace;
use function stripos;
use function strtoupper;
use function trim;

class SocketsImapConnection implements ImapConnectionInterface
{
    /**
     * @inheritdoc
     * @param array<string, mixed> $parameters
     */
    public function open(
        string $mailbox,
        string $username,
        string $password,
        int $options = 0,
        int $retries = 0,
        array $parameters = []
    ): mixed {
        // Parse mailbox string: {host:port/flags}folder
        if (!preg_match('/^\{([^:]+):(\d+)(.*?)\}(.*)$/', $mailbox, $matches)) {
            throw new ConnectionException("Invalid mailbox string format: $mailbox");
        }

        $host = $matches[1];
        $port = (int)$matches[2];
        $flags = $matches[3];
        $folder = $matches[4];

        $encryption = false;
        $certValidation = true;

        if (stripos($flags, '/ssl') !== false) {
            $encryption = 'ssl';
        } elseif (stripos($flags, '/tls') !== false) {
            $encryption = 'tls';
        }

        if (stripos($flags, '/novalidate-cert') !== false) {
            $certValidation = false;
        }

        $config = new Config();
        $protocol = new ImapProtocol($config, $certValidation, $encryption);

        if ($options & IMAP::OP_DEBUG) {
            $protocol->enableDebug();
        }

        try {
            $protocol->connect($host, $port);
            $protocol->login($username, $password);

            if ($folder) {
                // Determine if we should Select or Examine (Readonly)
                if ($options & IMAP::OP_READONLY) {
                    $protocol->examineFolder($folder);
                } else {
                    $protocol->selectFolder($folder);
                }
            }
        } catch (\Exception $e) {
            throw new ConnectionException($e->getMessage(), (int)$e->getCode(), $e);
        }

        return $protocol;
    }

    /**
     * @inheritdoc
     */
    public function close(mixed $stream, bool $expunge = false): bool
    {
        /** @var ImapProtocol $stream */
        try {
            if ($expunge) {
                $stream->expunge();
            }
            $stream->logout();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function ping(mixed $stream): bool
    {
        /** @var ImapProtocol $stream */
        try {
            return $stream->connected();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @inheritdoc
     * @return array<int, int>
     */
    public function search(mixed $stream, string $criteria, int $options = 0): array
    {
        /** @var ImapProtocol $stream */
        try {
            $params = $this->tokenizeSearchCriteria($criteria);
            if ($params === []) {
                $params = ['ALL'];
            }

            $uid = ($options === IMAP::SE_UID) ? IMAP::ST_UID : IMAP::ST_MSGN;

            $response = $stream->search($params, $uid);
            $ids = $response->validatedData();

            return is_array($ids) ? array_map('intval', $ids) : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Split IMAP search criteria into tokens while preserving quoted strings.
     *
     * @return array<int, string>
     */
    private function tokenizeSearchCriteria(string $criteria): array
    {
        $criteria = trim($criteria);
        if ($criteria === '') {
            return [];
        }

        $matches = [];
        preg_match_all('/"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"|[^\\s]+/', $criteria, $matches);
        $tokens = $matches[0];

        return array_values(array_filter(array_map('trim', $tokens), static fn(string $token): bool => $token !== ''));
    }

    /**
     * @inheritdoc
     * @return array<int, stdClass>
     */
    public function fetchOverview(mixed $stream, string $sequence, int $options = 0): array
    {
        /** @var ImapProtocol $stream */
        try {
            $uid = ($options === IMAP::FT_UID) ? IMAP::ST_UID : IMAP::ST_MSGN;
            $response = $stream->overview($sequence, $uid);
            $data = $response->validatedData();

            $results = [];
            foreach ($data as $msgId => $attributes) {
                $obj = new stdClass();
                $obj->uid = (int)$msgId;
                // ConnectionLibrary overview returns array of attributes
                // We map them to stdClass
                if (isset($attributes['uid'])) $obj->uid = (int)$attributes['uid'];
                if (isset($attributes['message_id'])) $obj->message_id = $attributes['message_id'];
                if (isset($attributes['subject'])) $obj->subject = $attributes['subject'];
                if (isset($attributes['from'])) $obj->from = is_array($attributes['from']) ? json_encode($attributes['from']) : $attributes['from']; // Simplified
                if (isset($attributes['to'])) $obj->to = is_array($attributes['to']) ? json_encode($attributes['to']) : $attributes['to'];
                if (isset($attributes['date'])) {
                     $dateObj = $attributes['date'];
                     if ($dateObj instanceof \DateTimeInterface) {
                         $obj->date = $dateObj->format('r');
                     } else {
                         $obj->date = (string)$dateObj;
                     }
                }
                if (isset($attributes['size'])) $obj->size = $attributes['size'];

                $flags = $attributes['flags'] ?? [];
                if (is_string($flags)) $flags = [$flags]; // Should be array

                $obj->seen = in_array('\\Seen', $flags) ? 1 : 0;
                $obj->answered = in_array('\\Answered', $flags) ? 1 : 0;
                $obj->flagged = in_array('\\Flagged', $flags) ? 1 : 0;
                $obj->deleted = in_array('\\Deleted', $flags) ? 1 : 0;
                $obj->draft = in_array('\\Draft', $flags) ? 1 : 0;
                $obj->recent = in_array('\\Recent', $flags) ? 1 : 0;

                $results[] = $obj;
            }
            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * @inheritdoc
     */
    public function numMsg(mixed $stream): int
    {
        /** @var ImapProtocol $stream */
        try {
           $response = $stream->search(['ALL']);
           return count($response->validatedData() ?? []);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * @inheritdoc
     */
    public function fetchStructure(mixed $stream, int $msgNumber, int $options = 0): object|false
    {
        /** @var ImapProtocol $stream */
        try {
            $uid = ($options === IMAP::FT_UID) ? IMAP::ST_UID : IMAP::ST_MSGN;
            $response = $stream->fetch('BODYSTRUCTURE', $msgNumber, null, $uid);

            $data = $response->validatedData();

            if (!is_array($data) || $data === []) {
                return false;
            }

            // For single-message BODYSTRUCTURE fetches, ImapProtocol returns the structure directly.
            // For multi-message fetches it returns a map keyed by UID/msg number.
            if (isset($data[$msgNumber]) && is_array($data[$msgNumber])) {
                $structData = $data[$msgNumber];
            } elseif (count($data) === 1) {
                $structData = reset($data);
            } else {
                $structData = $data;
            }

            if (!is_array($structData) || $structData === []) {
                return false;
            }

            // Some servers/wrappers return BODYSTRUCTURE as [0 => [actual-structure]].
            // Unwrap single nested wrappers before mapping.
            while (isset($structData[0]) && count($structData) === 1 && is_array($structData[0])) {
                $structData = $structData[0];
            }

            return StructureMapper::map($structData);

        } catch (\Throwable $e) {
            // echo "DEBUG fetchStructure: Exception - " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function headerInfo(mixed $stream, int $msgNumber, int $options = 0): object|false
    {
         return false;
    }

    /**
     * @inheritdoc
     */
    public function fetchHeader(mixed $stream, int $msgNumber, int $options = 0): string|false
    {
        /** @var ImapProtocol $stream */
        try {
             $uid = ($options === IMAP::FT_UID) ? IMAP::ST_UID : IMAP::ST_MSGN;
             $response = $stream->headers($msgNumber, "RFC822", $uid);
             $data = $response->validatedData();

             // ImapProtocol returns data in different formats:
             // - Single message: array with numeric index 0 containing the data
             // - Multiple messages: array with UID/msgnum as keys
             if (is_array($data)) {
                 // Check if this is a single-message response (has numeric key 0)
                 if (isset($data[0])) {
                     return $data[0];
                 }
                 if (count($data) === 1) {
                     $first = reset($data);
                     return is_string($first) ? $first : false;
                 }
                 // Multi-message response - use UID/msgnum as key
                 return $data[$msgNumber] ?? false;
             }

             // Scalar return
             return $data !== false ? $data : false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function parseHeader(string $rawHeader): object|false
    {
        try {
            $config = new Config();
            $header = new Header($rawHeader, $config);
            return $header->rfc822_parse_headers($rawHeader);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function fetchBody(mixed $stream, int $msgNumber, string $section, int $options = 0): string|false
    {
        /** @var ImapProtocol $stream */
        try {
            $uid = ($options & IMAP::FT_UID) ? IMAP::ST_UID : IMAP::ST_MSGN;

            $item = "BODY[$section]";
            if ($options & IMAP::FT_PEEK) {
                 $item = "BODY.PEEK[$section]";
            }

            $response = $stream->fetch([$item], $msgNumber, null, $uid);
            $data = $response->validatedData();

            // echo "DEBUG fetchBody: item = $item, msgNumber = $msgNumber\n";
            // echo "DEBUG fetchBody: data type = " . gettype($data) . "\n";
            // if (is_array($data)) {
            //     echo "DEBUG fetchBody: data keys = " . implode(', ', array_keys($data)) . "\n";
            //     echo "DEBUG fetchBody: full data dump:\n";
            //     var_dump($data);
            // }

            // ImapProtocol returns data in different formats:
            // - Single message: array with numeric index 0 containing the data
            // - Multiple messages: array with UID/msgnum as keys
            if (is_array($data)) {
                // Check if this is a single-message response (has numeric key 0)
                if (isset($data[0])) {
                    $body = $data[0];
                } elseif (count($data) === 1) {
                    $body = reset($data);
                } else {
                    // Multi-message response - use UID/msgnum as key
                    $body = $data[$msgNumber] ?? null;
                }
            } else {
                // Scalar return
                $body = $data;
            }

            return $body !== null && $body !== false ? $body : false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function body(mixed $stream, int $msgNumber, int $options = 0): string|false
    {
        // BODY[TEXT] returns body without headers, matching imap_body() behavior
        // BODY[] would return the entire RFC822 message including headers
        return $this->fetchBody($stream, $msgNumber, "TEXT", $options);
    }

    /**
     * @inheritdoc
     */
    public function saveBody(mixed $stream, mixed $file, int $msgNumber, string $section, int $options = 0): bool
    {
         $content = $this->fetchBody($stream, $msgNumber, $section, $options);
         if ($content === false) return false;

         if (is_resource($file)) {
             fwrite($file, $content);
         } elseif (is_string($file)) {
             file_put_contents($file, $content);
         }
         return true;
    }

    /**
     * @inheritdoc
     */
    public function setFlag(mixed $stream, string $sequence, string $flag, int $options = 0): bool
    {
        /** @var ImapProtocol $stream */
        try {
             $uid = ($options === IMAP::ST_UID) ? IMAP::ST_UID : IMAP::ST_MSGN;
             $command = $uid === IMAP::ST_UID ? "UID STORE" : "STORE";
             $streamParams = [$sequence, "+FLAGS",  $stream->escapeList([$flag]) ];
             $stream->requestAndResponse($command, $streamParams, true);
             return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function clearFlag(mixed $stream, string $sequence, string $flag, int $options = 0): bool
    {
         /** @var ImapProtocol $stream */
        try {
             $uid = ($options === IMAP::ST_UID) ? IMAP::ST_UID : IMAP::ST_MSGN;
             $command = $uid === IMAP::ST_UID ? "UID STORE" : "STORE";
             $streamParams = [$sequence, "-FLAGS",  $stream->escapeList([$flag]) ];
             $stream->requestAndResponse($command, $streamParams, true);
             return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @inheritdoc
     * @return array<int, stdClass>
     */
    public function mimeHeaderDecode(string $text): array
    {
        $result = [];
        // Regex to split MIME encoded words
        $parts = preg_split('/(\=\?.*?\?.\?.*?\?\=)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if (!is_array($parts)) return [];

        foreach ($parts as $part) {
            if (preg_match('/^\=\?(.*?)\?(.)\?(.*?)\?\=$/', $part, $matches)) {
                $charset = $matches[1];
                $encoding = strtoupper($matches[2]);
                $content = $matches[3];

                $decoded = $content;
                if ($encoding === 'B') {
                    $decoded = base64_decode($content);
                } elseif ($encoding === 'Q') {
                     $decoded = quoted_printable_decode(str_replace('_', ' ', $content));
                }

                $obj = new stdClass();
                $obj->charset = $charset;
                $obj->text = $decoded;
                $result[] = $obj;
            } else {
                // Ignore plain whitespace between encoded words?
                if (trim($part) === '' && count($result) > 0) continue;

                $obj = new stdClass();
                $obj->charset = 'default';
                $obj->text = $part;
                // Only add if not empty? or always?
                // imap_mime_header_decode behavior includes plain parts.
                $result[] = $obj;
            }
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function qprint(string $text): string
    {
        return quoted_printable_decode($text);
    }

    /**
     * @inheritdoc
     */
    public function base64(string $text): string
    {
         return base64_decode($text, true) ?: '';
    }

    /**
     * @inheritdoc
     */
    public function utf8(string $text, string $fromCharset, string $toCharset = 'UTF-8'): string
    {
         if ('' === trim($fromCharset)) {
            return $text;
        }
        $converted = @iconv($fromCharset, $toCharset . '//TRANSLIT', $text);
        return false === $converted ? $text : $converted;
    }

    /**
     * @inheritdoc
     * @return array<int, string>
     */
    public function errors(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function alertsClear(): void
    {
    }

    /**
     * @inheritdoc
     */
    public function status(mixed $stream, string $mailbox, int $options): object|false
    {
         /** @var ImapProtocol $stream */
        try {
             $params = [];
            if ($options & IMAP::SA_MESSAGES) $params[] = "MESSAGES";
            if ($options & IMAP::SA_RECENT) $params[] = "RECENT";
            if ($options & IMAP::SA_UIDNEXT) $params[] = "UIDNEXT";
            if ($options & IMAP::SA_UIDVALIDITY) $params[] = "UIDVALIDITY";
            if ($options & IMAP::SA_UNSEEN) $params[] = "UNSEEN";

            // Mailbox extraction from status might be tricky if reference/mailbox needed
            // But we can usually pass the mailbox we opened.
            // But status() takes $mailbox argument.

            $response = $stream->folderStatus($mailbox, $params);
            $data = $response->validatedData();

            $obj = new stdClass();
             if (isset($data['messages'])) $obj->messages = $data['messages'];
             if (isset($data['recent'])) $obj->recent = $data['recent'];
             if (isset($data['uidnext'])) $obj->uidnext = $data['uidnext'];
             if (isset($data['uidvalidity'])) $obj->uidvalidity = $data['uidvalidity'];
             if (isset($data['unseen'])) $obj->unseen = $data['unseen'];

             return $obj;
        } catch (\Exception $e) {
            return false;
        }
    }
}

class StructureMapper {
    /**
     * Recursively map array structure to stdClass compatible with imap_fetchstructure
     * @param array<int, mixed> $structure
     */
    public static function map(array $structure): stdClass {
        // Some responses wrap the actual structure into a single indexed array.
        while (isset($structure[0]) && count($structure) === 1 && is_array($structure[0])) {
            $structure = $structure[0];
        }

        $obj = new stdClass();

        // Basic mapping based on RFC 3501 and PHP imap_fetchstructure doc
        // Structure is [type, subtype, params, id, description, encoding, size, ...]
        // OR for multipart: [part1, part2, ..., subtype]

        // Check if it's multipart (nested arrays) or flattened attributes
        // Multipart structure ends with subtype string.

        if (is_array($structure[0])) {
            // Multipart
            $obj->type = 1; // TYPEMULTIPART

            // All elements except last are parts
            $obj->parts = [];
            $count = count($structure);
            for($i=0; $i < $count-1; $i++) {
                if (is_array($structure[$i])) {
                    $obj->parts[] = self::map($structure[$i]);
                }
            }
            $obj->subtype = $structure[$count-1];

            // Extension data might follow?
            // RFC 3501: body-type-mpart = 1*body ( "subtype" [parameters [disposition ...]])
            // ConnectionLibrary might return this.
            // But if $structure has more items after subtype?
            // Let's assume ConnectionLibrary parser handles it.
            // But wait, ImapProtocol::decodeLine output:
            // ( (part1) (part2) "MIXED" ("boundary" "foo") )
            // decodeLine -> [ [part1], [part2], "MIXED", ["boundary", "foo"] ]

            // So structure might have more elements after subtype.
            // Element index:
            // 0..n-1: parts (arrays)
            // n: subtype
            // n+1: parameters (array of key,value)
            // n+2: disposition
            // ...

            // Let's find index where string starts (subtype)
            $subtypeIndex = -1;
            foreach($structure as $k => $v) {
                if (is_string($v) && $v !== "NIL") {
                     // Subtype is the first string after arrays?
                     $subtypeIndex = (int)$k;
                     break;
                }
            }

            if ($subtypeIndex > -1) {
                // Parts are 0 to subtypeIndex-1
                $obj->parts = [];
                for($i=0; $i < $subtypeIndex; $i++) {
                     $obj->parts[] = self::map($structure[$i]);
                }
                $obj->subtype = $structure[$subtypeIndex];
            }

            // Handling parameters
             if (isset($structure[$subtypeIndex+1]) && is_array($structure[$subtypeIndex+1])) {
                 $obj->ifparameters = 1;
                 $obj->parameters = self::mapParams($structure[$subtypeIndex+1]);
             }

             // Disposition
             if (isset($structure[$subtypeIndex+2]) && is_array($structure[$subtypeIndex+2])) {
                 // Dispostion is (string params)
                 $obj->ifdisposition = 1;
                 $obj->disposition = $structure[$subtypeIndex+2][0] ?? null;
                 if (isset($structure[$subtypeIndex+2][1])) {
                     $obj->ifdparameters = 1;
                     $obj->dparameters = self::mapParams($structure[$subtypeIndex+2][1]);
                 }
             }

        } else {
            // Single part
            // [type, subtype, params, id, description, encoding, size, lines, md5, disposition, language, location]
            // Types string map to int?
            // "TEXT", "PLAIN", ...
            // imap_fetchstructure returns INT type.
            $typeMap = [
                'TEXT' => 0, 'MULTIPART' => 1, 'MESSAGE' => 2, 'APPLICATION' => 3,
                'AUDIO' => 4, 'IMAGE' => 5, 'VIDEO' => 6, 'OTHER' => 7
            ];

            $typeStr = strtoupper($structure[0] ?? 'TEXT');
            $obj->type = $typeMap[$typeStr] ?? 7;

            $obj->subtype = $structure[1] ?? null;

            if (isset($structure[2]) && is_array($structure[2])) {
                $obj->ifparameters = 1;
                $obj->parameters = self::mapParams($structure[2]);
            }

            $obj->id = ($structure[3] ?? "NIL") !== "NIL" ? $structure[3] : null;
            if ($obj->id) $obj->ifid = 1;

            $obj->description = ($structure[4] ?? "NIL") !== "NIL" ? $structure[4] : null;
            if ($obj->description) $obj->ifdescription = 1;

            $encodingStr = strtoupper($structure[5] ?? '7BIT');
            $encodingMap = [
                '7BIT' => 0, '8BIT' => 1, 'BINARY' => 2, 'BASE64' => 3, 'QUOTED-PRINTABLE' => 4, 'OTHER' => 5
            ];
            $obj->encoding = $encodingMap[$encodingStr] ?? 5;

            $obj->size = (int)($structure[6] ?? 0);
            $obj->bytes = $obj->size;

            $obj->lines = isset($structure[7]) && $structure[7] !== "NIL" ? (int)$structure[7] : null;

            // Extended fields (md5, disposition, etc)
             // Disposition is usually index 8 or 9 depending on lines presence?
             // RFC body-type-1part: ... size [lines] [md5] [disposition] [language] [location]
             // Lines is only for type TEXT.
             // If type is TEXT, lines is at 7. MD5 at 8. Disposition at 9.
             // If not TEXT, MD5 at 7, Disposition at 8.

             $nextIndex = 7;
             if ($obj->type === 0) { // TEXT
                 $nextIndex = 8; // Lines was 7
             }

             // MD5
             $nextIndex++; // Skip MD5 for now

             // Disposition
             if (isset($structure[$nextIndex]) && is_array($structure[$nextIndex])) {
                  $obj->ifdisposition = 1;
                 $obj->disposition = $structure[$nextIndex][0] ?? null;
                 if (isset($structure[$nextIndex][1])) {
                     $obj->ifdparameters = 1;
                     $obj->dparameters = self::mapParams($structure[$nextIndex][1]);
                 }
             }
        }

        return $obj;
    }

    /**
     * @param array<int, string> $params
     * @return array<int, stdClass>
     */
    public static function mapParams(array $params): array {
        $result = [];
        // Params are ["key", "value", "key2", "value2"]
        if (count($params) % 2 !== 0) return [];

        for($i=0; $i < count($params); $i+=2) {
            $p = new stdClass();
            $p->attribute = $params[$i];
            $p->value = $params[$i+1];
            $result[] = $p;
        }
        return $result;
    }
}
