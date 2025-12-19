<?php

declare(strict_types=1);

namespace Yai\Ymap\Connection;

/**
 * Abstraction layer for IMAP protocol operations.
 * Allows swapping between ext-imap (PHP < 8.4) and pure PHP implementations (PHP 8.4+).
 */
interface ImapConnectionInterface
{
    /**
     * Open connection to IMAP server
     *
     * @param string $mailbox IMAP mailbox path (e.g., {imap.gmail.com:993/imap/ssl}INBOX)
     * @param string $username Username or email
     * @param string $password Password or app password
     * @param int $options Bitmask of options (OP_READONLY, OP_ANONYMOUS, etc.)
     * @param int $retries Number of connection retries
     * @param array<string, mixed> $parameters Additional parameters (TLS context, authenticators, etc.)
     * @return mixed Connection resource or object
     * @throws \Yai\Ymap\Exceptions\ConnectionException
     */
    public function open(
        string $mailbox,
        string $username,
        string $password,
        int $options = 0,
        int $retries = 0,
        array $parameters = []
    ): mixed;

    /**
     * Close IMAP connection
     *
     * @param mixed $stream Connection resource/object
     * @param bool $expunge Expunge deleted messages before closing
     * @return bool Success status
     */
    public function close(mixed $stream, bool $expunge = false): bool;

    /**
     * Check if connection is alive
     *
     * @param mixed $stream Connection resource/object
     * @return bool True if connection is active
     */
    public function ping(mixed $stream): bool;

    /**
     * Search for message UIDs matching criteria
     *
     * @param mixed $stream Connection resource/object
     * @param string $criteria IMAP search criteria (e.g., "UNSEEN SINCE 1-Jan-2024")
     * @param int $options Search options (SE_UID, SE_FREE, etc.)
     * @return array<int> Array of message UIDs
     */
    public function search(mixed $stream, string $criteria, int $options = 0): array;

    /**
     * Fetch overview information for one or more messages.
     *
     * @param mixed $stream Connection resource/object
     * @param string $sequence Sequence set or UID list
     * @param int $options FT_UID for UID-based fetch
     * @return array<object> Overview objects
     */
    public function fetchOverview(mixed $stream, string $sequence, int $options = 0): array;

    /**
     * Get total number of messages in mailbox
     *
     * @param mixed $stream Connection resource/object
     * @return int Message count
     */
    public function numMsg(mixed $stream): int;

    /**
     * Fetch message structure
     *
     * @param mixed $stream Connection resource/object
     * @param int $msgNumber Message sequence number or UID
     * @param int $options FT_UID for UID-based fetch
     * @return object|false Structure object or false on failure
     */
    public function fetchStructure(mixed $stream, int $msgNumber, int $options = 0): object|false;

    /**
     * Fetch message headers
     *
     * @param mixed $stream Connection resource/object
     * @param int $msgNumber Message sequence number or UID
     * @param int $options FT_UID for UID-based fetch
     * @return object|false Header object or false on failure
     */
    public function headerInfo(mixed $stream, int $msgNumber, int $options = 0): object|false;

    /**
     * Fetch raw RFC822 headers.
     *
     * @param mixed $stream Connection resource/object
     * @param int $msgNumber Message sequence number or UID
     * @param int $options Fetch options (FT_UID, FT_PEEK, etc.)
     * @return string|false Raw header string or false on failure
     */
    public function fetchHeader(mixed $stream, int $msgNumber, int $options = 0): string|false;

    /**
     * Parse raw RFC822 headers into an object representation.
     *
     * @param string $rawHeader Raw header string
     * @return object|false Parsed header object or false on failure
     */
    public function parseHeader(string $rawHeader): object|false;

    /**
     * Fetch message body or body part
     *
     * @param mixed $stream Connection resource/object
     * @param int $msgNumber Message sequence number or UID
     * @param string $section Section identifier (e.g., "1", "1.2", "TEXT")
     * @param int $options Fetch options (FT_UID, FT_PEEK, etc.)
     * @return string|false Body content or false on failure
     */
    public function fetchBody(mixed $stream, int $msgNumber, string $section, int $options = 0): string|false;

    /**
     * Fetch the full body of a message.
     *
     * @param mixed $stream Connection resource/object
     * @param int $msgNumber Message sequence number or UID
     * @param int $options Fetch options (FT_UID, FT_PEEK, etc.)
     * @return string|false Body content or false on failure
     */
    public function body(mixed $stream, int $msgNumber, int $options = 0): string|false;

    /**
     * Save message body or part to file
     *
     * @param mixed $stream Connection resource/object
     * @param mixed $file File handle resource
     * @param int $msgNumber Message sequence number or UID
     * @param string $section Section identifier
     * @param int $options Fetch options (FT_UID, FT_PEEK, etc.)
     * @return bool Success status
     */
    public function saveBody(mixed $stream, mixed $file, int $msgNumber, string $section, int $options = 0): bool;

    /**
     * Set message flags
     *
     * @param mixed $stream Connection resource/object
     * @param string $sequence Sequence set (e.g., "1,3,5" or "1:10")
     * @param string $flag Flag to set (e.g., "\\Seen", "\\Answered")
     * @param int $options ST_UID for UID-based operation
     * @return bool Success status
     */
    public function setFlag(mixed $stream, string $sequence, string $flag, int $options = 0): bool;

    /**
     * Clear message flags
     *
     * @param mixed $stream Connection resource/object
     * @param string $sequence Sequence set
     * @param string $flag Flag to clear
     * @param int $options ST_UID for UID-based operation
     * @return bool Success status
     */
    public function clearFlag(mixed $stream, string $sequence, string $flag, int $options = 0): bool;

    /**
     * Decode MIME-encoded text
     *
     * @param string $text Encoded text
     * @return array<object> Array of decoded parts
     */
    public function mimeHeaderDecode(string $text): array;

    /**
     * Decode quoted-printable string
     *
     * @param string $text Quoted-printable encoded string
     * @return string Decoded string
     */
    public function qprint(string $text): string;

    /**
     * Decode base64 string
     *
     * @param string $text Base64 encoded string
     * @return string Decoded string
     */
    public function base64(string $text): string;

    /**
     * Convert encoding
     *
     * @param string $text Input text
     * @param string $fromCharset Source charset
     * @param string $toCharset Target charset
     * @return string Converted text
     */
    public function utf8(string $text, string $fromCharset, string $toCharset = 'UTF-8'): string;

    /**
     * Get last IMAP errors
     *
     * @return array<string> Array of error messages
     */
    public function errors(): array;

    /**
     * Clear error stack
     *
     * @return void
     */
    public function alertsClear(): void;

    /**
     * Get mailbox status
     *
     * @param mixed $stream Connection resource/object
     * @param string $mailbox Mailbox name
     * @param int $options Status options (SA_MESSAGES, SA_RECENT, etc.)
     * @return object|false Status object or false on failure
     */
    public function status(mixed $stream, string $mailbox, int $options): object|false;
}
