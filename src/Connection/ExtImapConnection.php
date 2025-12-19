<?php declare(strict_types=1);

namespace Yai\Ymap\Connection;

use Yai\Ymap\Exceptions\ConnectionException;
use const CL_EXPUNGE;
use function array_filter;
use function array_unique;
use function array_values;
use function base64_decode;
use function iconv;
use function implode;
use function imap_alerts;
use function imap_base64;
use function imap_body;
use function imap_clearflag_full;
use function imap_close;
use function imap_errors;
use function imap_fetch_overview;
use function imap_fetchbody;
use function imap_fetchheader;
use function imap_fetchstructure;
use function imap_headerinfo;
use function imap_last_error;
use function imap_mime_header_decode;
use function imap_num_msg;
use function imap_open;
use function imap_ping;
use function imap_qprint;
use function imap_rfc822_parse_headers;
use function imap_savebody;
use function imap_search;
use function imap_setflag_full;
use function imap_status;
use function imap_utf8;
use function quoted_printable_decode;
use function sprintf;
use function trim;

/**
 * IMAP connection adapter that proxies to the ext-imap functions.
 */
final class ExtImapConnection implements ImapConnectionInterface
{
    public function open(
        string $mailbox,
        string $username,
        string $password,
        int $options = 0,
        int $retries = 0,
        array $parameters = []
    ): mixed {
        $resource = @imap_open($mailbox, $username, $password, $options, $retries, $parameters);

        if (false === $resource) {
            throw new ConnectionException(
                sprintf('Unable to connect to mailbox "%s": %s', $mailbox, $this->formatLastError())
            );
        }

        return $resource;
    }

    public function close(mixed $stream, bool $expunge = false): bool
    {
        return imap_close($stream, $expunge ? CL_EXPUNGE : 0);
    }

    public function ping(mixed $stream): bool
    {
        return imap_ping($stream);
    }

    public function search(mixed $stream, string $criteria, int $options = 0): array
    {
        $result = imap_search($stream, $criteria, $options);

        return false === $result ? [] : $result;
    }

    public function fetchOverview(mixed $stream, string $sequence, int $options = 0): array
    {
        $result = imap_fetch_overview($stream, $sequence, $options);

        return false === $result ? [] : $result;
    }

    public function numMsg(mixed $stream): int
    {
        $count = imap_num_msg($stream);

        return false === $count ? 0 : (int) $count;
    }

    public function fetchStructure(mixed $stream, int $msgNumber, int $options = 0): object|false
    {
        return imap_fetchstructure($stream, $msgNumber, $options);
    }

    public function headerInfo(mixed $stream, int $msgNumber, int $options = 0): object|false
    {
        return imap_headerinfo($stream, $msgNumber);
    }

    public function fetchHeader(mixed $stream, int $msgNumber, int $options = 0): string|false
    {
        return imap_fetchheader($stream, $msgNumber, $options);
    }

    public function parseHeader(string $rawHeader): object|false
    {
        return imap_rfc822_parse_headers($rawHeader);
    }

    public function fetchBody(mixed $stream, int $msgNumber, string $section, int $options = 0): string|false
    {
        return imap_fetchbody($stream, $msgNumber, $section, $options);
    }

    public function body(mixed $stream, int $msgNumber, int $options = 0): string|false
    {
        return imap_body($stream, $msgNumber, $options);
    }

    public function saveBody(
        mixed $stream,
        mixed $file,
        int $msgNumber,
        string $section,
        int $options = 0
    ): bool {
        return imap_savebody($stream, $file, $msgNumber, $section, $options);
    }

    public function setFlag(mixed $stream, string $sequence, string $flag, int $options = 0): bool
    {
        return imap_setflag_full($stream, $sequence, $flag, $options);
    }

    public function clearFlag(mixed $stream, string $sequence, string $flag, int $options = 0): bool
    {
        return imap_clearflag_full($stream, $sequence, $flag, $options);
    }

    public function mimeHeaderDecode(string $text): array
    {
        $decoded = imap_mime_header_decode($text);

        return false === $decoded ? [] : $decoded;
    }

    public function qprint(string $text): string
    {
        $decoded = imap_qprint($text);

        if (false !== $decoded) {
            return $decoded;
        }

        return quoted_printable_decode($text);
    }

    public function base64(string $text): string
    {
        $decoded = imap_base64($text);

        if (false !== $decoded && '' !== $decoded) {
            return $decoded;
        }

        return base64_decode($text, true) ?: '';
    }

    public function utf8(string $text, string $fromCharset, string $toCharset = 'UTF-8'): string
    {
        if ('' === trim($fromCharset)) {
            return $text;
        }

        $converted = iconv($fromCharset, $toCharset . '//TRANSLIT', $text);

        if (false !== $converted) {
            return $converted;
        }

        return imap_utf8($text);
    }

    public function errors(): array
    {
        $errors = imap_errors() ?: [];
        $lastError = imap_last_error();

        if (null !== $lastError && '' !== $lastError) {
            $errors[] = $lastError;
        }

        return array_values(array_unique(array_filter($errors)));
    }

    public function alertsClear(): void
    {
        imap_alerts();
    }

    public function status(mixed $stream, string $mailbox, int $options): object|false
    {
        return imap_status($stream, $mailbox, $options);
    }

    private function formatLastError(): string
    {
        $errors = $this->errors();

        if ([] === $errors) {
            return 'Unknown IMAP error';
        }

        return implode('; ', $errors);
    }
}
