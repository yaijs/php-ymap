<?php declare(strict_types=1);

namespace Yai\Ymap;

use DateTimeImmutable;
use Exception;
use Yai\Ymap\Exceptions\ConnectionException;
use Yai\Ymap\Exceptions\ImapException;
use Yai\Ymap\Exceptions\MessageFetchException;
use const ENC7BIT;
use const ENC8BIT;
use const ENCBASE64;
use const ENCBINARY;
use const ENCQUOTEDPRINTABLE;
use const FT_PEEK;
use const FT_UID;
use const SE_UID;
use const SORT_NUMERIC;
use const ST_UID;
use const TYPEMESSAGE;
use const TYPEMULTIPART;
use const TYPETEXT;
use Yai\Ymap\Connection\ExtImapConnection;
use Yai\Ymap\Connection\ImapConnectionInterface;
use function array_filter;
use function array_map;
use function array_unique;
use function base64_decode;
use function count;
use function explode;
use function iconv;
use function implode;
use function in_array;
use function is_array;
use function preg_replace;
use function preg_split;
use function quoted_printable_decode;
use function sort;
use function sprintf;
use function strcasecmp;
use function strlen;
use function strtolower;
use function strtoupper;
use function trim;

/**
 * Minimal IMAP client that can read emails and toggle read/unread flags.
 */
final class ImapClient implements ImapClientInterface
{
    private const DEFAULT_ATTACHMENT_NAME = 'attachment';

    private const HEADER_FOLDING_PATTERN = "/\r\n[ \t]+/";

    private ConnectionConfig $config;

    private string $targetEncoding;

    private ImapConnectionInterface $connection;

    private mixed $stream = null;

    public function __construct(
        ConnectionConfig $config,
        string $targetEncoding = 'UTF-8',
        ?ImapConnectionInterface $connection = null
    ) {
        $this->config = $config;
        $this->targetEncoding = $targetEncoding;
        $this->connection = $connection ?? new ExtImapConnection();
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * @throws ConnectionException
     */
    public function connect(): void
    {
        if (null !== $this->stream && false !== $this->stream) {
            return;
        }

        try {
            $resource = $this->connection->open(
                $this->config->getMailboxPath(),
                $this->config->getUsername(),
                $this->config->getPassword(),
                $this->config->getOptions(),
                $this->config->getRetries(),
                $this->config->getParameters()
            );
        } catch (ConnectionException $exception) {
            throw new ConnectionException(
                sprintf('Unable to connect to mailbox "%s": %s', $this->config->getMailboxPath(), $exception->getMessage()),
                (int) $exception->getCode(),
                $exception
            );
        }

        if (false === $resource) {
            throw new ConnectionException(
                sprintf('Unable to connect to mailbox "%s": %s', $this->config->getMailboxPath(), $this->collectLastError())
            );
        }

        $this->stream = $resource;
    }

    public function disconnect(bool $expunge = false): void
    {
        if (null === $this->stream) {
            return;
        }

        $this->connection->close($this->stream, $expunge);
        $this->stream = null;
    }

    /**
     * @return int[]
     *
     * @throws ConnectionException
     */
    public function search(string $criteria = 'ALL'): array
    {
        $stream = $this->stream();
        $result = $this->connection->search($stream, $criteria, SE_UID);
        sort($result, SORT_NUMERIC);

        return $result;
    }

    /**
     * @return int[]
     *
     * @throws ConnectionException
     */
    public function getUnreadUids(): array
    {
        return $this->search('UNSEEN');
    }

    /**
     * @throws ConnectionException
     * @throws MessageFetchException
     */
    public function fetchMessage(int $uid, ?FetchOptions $options = null): Message
    {
        $options ??= FetchOptions::everything();
        $stream = $this->stream();
        $rawHeader = $this->connection->fetchHeader($stream, $uid, FT_UID);

        if (false === $rawHeader) {
            throw new MessageFetchException(
                sprintf('Unable to fetch headers for UID %d: %s', $uid, $this->collectLastError())
            );
        }

        /** @var \stdClass|false $headerObject */
        $headerObject = $this->connection->parseHeader($rawHeader);
        if (false === $headerObject) {
            throw new MessageFetchException(
                sprintf('Unable to parse headers for UID %d: %s', $uid, $this->collectLastError())
            );
        }

        $message = new Message($uid);
        $message->setHeaders($this->parseHeaderLines($rawHeader));
        $message->setSubject($this->decodeMimeHeader($headerObject->subject ?? null));
        $message->setDate($this->buildDate($headerObject->date ?? null));

        foreach ($this->mapAddressList($headerObject->from ?? []) as $address) {
            $message->addFrom($address);
        }

        foreach ($this->mapAddressList($headerObject->to ?? []) as $address) {
            $message->addTo($address);
        }

        foreach ($this->mapAddressList($headerObject->cc ?? []) as $address) {
            $message->addCc($address);
        }

        foreach ($this->mapAddressList($headerObject->bcc ?? []) as $address) {
            $message->addBcc($address);
        }

        foreach ($this->mapAddressList($headerObject->reply_to ?? []) as $address) {
            $message->addReplyTo($address);
        }

        $structure = $this->connection->fetchStructure($stream, $uid, FT_UID);
        if (false === $structure) {
            throw new MessageFetchException(
                sprintf('Unable to fetch structure for UID %d: %s', $uid, $this->collectLastError())
            );
        }

        $overview = $this->connection->fetchOverview($stream, (string) $uid, FT_UID);
        if (isset($overview[0])) {
            $message->setSeen(!empty($overview[0]->seen));
            $message->setAnswered(!empty($overview[0]->answered));
        }

        $this->parseStructure($message, $structure, $uid, $options);

        $calculatedSize = strlen($message->getTextBody() ?? '') + strlen($message->getHtmlBody() ?? '');
        foreach ($message->getAttachments() as $attachment) {
            $calculatedSize += $attachment->getSize();
        }

        if (0 === $calculatedSize && isset($overview[0]->size)) {
            $calculatedSize = (int) $overview[0]->size;
        }

        $message->setSize($calculatedSize);

        return $message;
    }

    /**
     * @param int|int[] $uids
     *
     * @throws ConnectionException
     * @throws ImapException
     */
    public function markAsRead($uids): void
    {
        $this->setFlag($uids, '\\Seen');
    }

    /**
     * @param int|int[] $uids
     *
     * @throws ConnectionException
     * @throws ImapException
     */
    public function markAsUnread($uids): void
    {
        $this->clearFlag($uids, '\\Seen');
    }

    /**
     * @param int|int[] $uids
     *
     * @throws ConnectionException
     * @throws ImapException
     */
    public function markAsAnswered($uids): void
    {
        $this->setFlag($uids, '\\Answered');
    }

    /**
     * @param int|int[] $uids
     *
     * @throws ConnectionException
     * @throws ImapException
     */
    public function markAsUnanswered($uids): void
    {
        $this->clearFlag($uids, '\\Answered');
    }

    /**
     * @param int|int[] $uids
     *
     * @throws ConnectionException
     * @throws ImapException
     */
    public function setFlag($uids, string $flag): void
    {
        $sequence = $this->buildSequence($uids);
        if ('' === $sequence) {
            return;
        }

        $stream = $this->stream();
        if (false === $this->connection->setFlag($stream, $sequence, $flag, ST_UID)) {
            throw new ImapException(
                sprintf('Unable to set flag %s on %s: %s', $flag, $sequence, $this->collectLastError())
            );
        }
    }

    /**
     * @param int|int[] $uids
     *
     * @throws ConnectionException
     * @throws ImapException
     */
    public function clearFlag($uids, string $flag): void
    {
        $sequence = $this->buildSequence($uids);
        if ('' === $sequence) {
            return;
        }

        $stream = $this->stream();
        if (false === $this->connection->clearFlag($stream, $sequence, $flag, ST_UID)) {
            throw new ImapException(
                sprintf('Unable to clear flag %s on %s: %s', $flag, $sequence, $this->collectLastError())
            );
        }
    }

    /**
     * Stream attachment content directly to a file or resource using IMAP.
     *
     * @param resource|string $destination
     *
     * @throws ConnectionException
     * @throws ImapException
     */
    public function saveAttachmentTo(int $uid, Attachment $attachment, $destination): void
    {
        $partNumber = $attachment->getPartNumber();
        if (null === $partNumber) {
            throw new ImapException('Attachment is missing the part number required for streaming.');
        }

        $stream = $this->stream();
        $result = $this->connection->saveBody($stream, $destination, $uid, $partNumber, FT_UID | FT_PEEK);

        if (false === $result) {
            throw new ImapException(
                sprintf('Unable to stream attachment %s: %s', $attachment->getFilename(), $this->collectLastError())
            );
        }
    }

    /**
     * @throws ConnectionException
     */
    private function stream(): mixed
    {
        if (null === $this->stream) {
            $this->connect();
        }

        return $this->stream;
    }

    private function collectLastError(): string
    {
        $errors = $this->connection->errors();
        $this->connection->alertsClear();
        $message = implode('; ', array_unique(array_filter($errors)));

        return $message ?: 'Unknown IMAP error';
    }

    /**
     * @param int|int[]|null $uids
     */
    private function buildSequence(int|array|null $uids): string
    {
        if (is_array($uids)) {
            $uids = array_filter(array_map('intval', $uids), static fn(int $value): bool => $value > 0);
        } elseif (null === $uids) {
            return '';
        } else {
            $uids = [(int) $uids];
        }

        if ([] === $uids) {
            return '';
        }

        return implode(',', $uids);
    }

    private function parseStructure(Message $message, object $structure, int $uid, FetchOptions $options): void
    {
        $isSinglePartMessage = empty($structure->parts);

        if (!$isSinglePartMessage) {
            foreach ($structure->parts as $index => $part) {
                $this->parsePart($message, $part, $uid, (string) ($index + 1), false, $options);
            }

            return;
        }

        $this->parsePart($message, $structure, $uid, '1', true, $options);
    }

    private function parsePart(
        Message $message,
        object $part,
        int $uid,
        string $partNumber,
        bool $isSinglePartMessage,
        FetchOptions $options
    ): void
    {
        // If this part has sub-parts, recurse into them and DON'T fetch body for this container
        if (!empty($part->parts)) {
            foreach ($part->parts as $index => $subPart) {
                $this->parsePart($message, $subPart, $uid, $partNumber . '.' . ($index + 1), false, $options);
            }
            // Don't fetch body for multipart containers - they contain MIME boundaries, not content
            return;
        }

        if ($this->isAttachmentPart($part)) {
            if (!$options->shouldFetchAttachments()) {
                return;
            }

            $attachment = $this->createAttachment($part, $uid, $partNumber, $isSinglePartMessage, $options);
            if (null !== $attachment) {
                $message->addAttachment($attachment);
            }

            return;
        }

        $needsText = $options->shouldFetchTextBody() && $this->isTextPart($part, 'PLAIN');
        $needsHtml = $options->shouldFetchHtmlBody() && $this->isTextPart($part, 'HTML');

        if (!$needsText && !$needsHtml) {
            return;
        }

        $body = $this->fetchPartBody($uid, $partNumber, $isSinglePartMessage);
        if (null === $body) {
            return;
        }

        $decodedBody = $this->decodeBody($body, (int) ($part->encoding ?? ENC7BIT));

        if ($needsText) {
            $message->appendTextBody($this->convertEncoding($decodedBody, $this->extractCharset($part)));
        } elseif ($needsHtml) {
            $message->appendHtmlBody($this->convertEncoding($decodedBody, $this->extractCharset($part)));
        }
    }

    private function fetchPartBody(int $uid, string $partNumber, bool $singlePartMessage): ?string
    {
        $stream = $this->stream();

        if ($singlePartMessage && '1' === $partNumber) {
            $body = $this->connection->body($stream, $uid, FT_UID | FT_PEEK);
        } else {
            $body = $this->connection->fetchBody($stream, $uid, $partNumber, FT_UID | FT_PEEK);
        }

        if (false === $body || '' === $body) {
            return null;
        }

        return $body;
    }

    private function decodeBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            ENCBASE64 => base64_decode($body, true) ?: '',
            ENCQUOTEDPRINTABLE => quoted_printable_decode($body),
            ENCBINARY => $body,
            ENC8BIT, ENC7BIT => $body,
            default => $body,
        };
    }

    private function isAttachmentPart(object $part): bool
    {
        // Text parts (text/plain, text/html) with inline disposition are body content, not attachments
        if ($this->isTextPart($part, 'PLAIN') || $this->isTextPart($part, 'HTML')) {
            // Only treat as attachment if explicitly marked as ATTACHMENT (not INLINE)
            if (isset($part->disposition)) {
                $disposition = strtoupper((string) $part->disposition);
                if ('ATTACHMENT' === $disposition) {
                    return true;
                }
            }
            // Text parts without ATTACHMENT disposition are body content
            return false;
        }

        // For non-text parts, INLINE or ATTACHMENT disposition means it's an attachment
        if (isset($part->disposition)) {
            $disposition = strtoupper((string) $part->disposition);
            if (in_array($disposition, ['ATTACHMENT', 'INLINE'], true)) {
                return true;
            }
        }

        foreach (['dparameters', 'parameters'] as $property) {
            if (empty($part->{$property})) {
                continue;
            }

            foreach ($part->{$property} as $parameter) {
                $attribute = strtolower((string) ($parameter->attribute ?? ''));
                if (in_array($attribute, ['filename', 'name'], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isInlinePart(object $part): bool
    {
        if (!isset($part->disposition)) {
            return false;
        }

        return 'INLINE' === strtoupper((string) $part->disposition);
    }

    private function isTextPart(object $part, string $subtype): bool
    {
        return TYPETEXT === ($part->type ?? null)
            && strtoupper($subtype) === strtoupper((string) ($part->subtype ?? ''));
    }

    private function resolveAttachmentName(object $part, string $fallback): string
    {
        $name = $this->extractParameter($part, 'filename') ?? $this->extractParameter($part, 'name');
        if (null !== $name) {
            $decoded = $this->decodeMimeHeader($name) ?? self::DEFAULT_ATTACHMENT_NAME;

            return $this->sanitizeFilename($decoded);
        }

        return $this->sanitizeFilename(sprintf('%s-%s', self::DEFAULT_ATTACHMENT_NAME, $fallback));
    }

    private function sanitizeFilename(string $filename): string
    {
        $sanitized = preg_replace('/[^\w\-. ]+/', '_', $filename) ?? '';
        $sanitized = trim($sanitized, " \t\n\r\0\x0B._-");

        return $sanitized ?: self::DEFAULT_ATTACHMENT_NAME;
    }

    private function resolveMimeType(object $part): string
    {
        $primaryTypeMap = [
            TYPETEXT => 'text',
            TYPEMULTIPART => 'multipart',
            TYPEMESSAGE => 'message',
            3 => 'application',
            4 => 'audio',
            5 => 'image',
            6 => 'video',
            7 => 'other',
        ];

        $primary = $primaryTypeMap[$part->type ?? 0] ?? 'application';
        $subtype = strtolower((string) ($part->subtype ?? 'octet-stream'));

        return sprintf('%s/%s', $primary, $subtype);
    }

    private function extractParameter(object $part, string $name): ?string
    {
        foreach (['dparameters', 'parameters'] as $property) {
            if (empty($part->{$property})) {
                continue;
            }

            foreach ($part->{$property} as $parameter) {
                if ($name === strtolower((string) ($parameter->attribute ?? ''))) {
                    return (string) ($parameter->value ?? null);
                }
            }
        }

        return null;
    }

    private function extractCharset(object $part): ?string
    {
        if (empty($part->parameters)) {
            return null;
        }

        foreach ($part->parameters as $parameter) {
            if ('charset' === strtolower((string) ($parameter->attribute ?? ''))) {
                return (string) $parameter->value;
            }
        }

        return null;
    }

    private function convertEncoding(string $text, ?string $fromEncoding): string
    {
        $sourceEncoding = $fromEncoding ?: $this->targetEncoding;

        if (0 === strcasecmp($sourceEncoding, $this->targetEncoding)) {
            return $text;
        }

        $converted = @iconv($sourceEncoding, $this->targetEncoding . '//TRANSLIT', $text);

        return false === $converted ? $text : $converted;
    }

    /**
     * @param array<int, object> $list
     *
     * @return MessageAddress[]
     */
    private function mapAddressList(array $list): array
    {
        $addresses = [];

        foreach ($list as $entry) {
            $mailbox = $entry->mailbox ?? null;
            $host = $entry->host ?? null;

            if (!$mailbox || !$host) {
                continue;
            }

            $email = sprintf('%s@%s', $mailbox, $host);
            $addresses[] = new MessageAddress($email, $this->decodeMimeHeader($entry->personal ?? null));
        }

        return $addresses;
    }

    private function decodeMimeHeader(?string $value): ?string
    {
        if (null === $value || '' === $value) {
            return $value;
        }

        $decoded = $this->connection->mimeHeaderDecode($value);
        if ([] === $decoded) {
            return $value;
        }
        $result = '';

        foreach ($decoded as $segment) {
            $charset = $segment->charset ?? 'default';
            $text = isset($segment->text) ? (string) $segment->text : '';

            if ('default' !== strtolower($charset) && '' !== $charset) {
                $converted = @iconv($charset, $this->targetEncoding . '//TRANSLIT', $text);
                $result .= false === $converted ? $text : $converted;
            } else {
                $result .= $text;
            }
        }

        return $result;
    }

    private function buildDate(?string $value): ?DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception $exception) {
            return null;
        }
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaderLines(string $rawHeader): array
    {
        $normalized = preg_replace(self::HEADER_FOLDING_PATTERN, ' ', trim($rawHeader)) ?? '';
        $lines = preg_split("/\r\n/", $normalized) ?: [];
        $result = [];

        foreach ($lines as $line) {
            if ('' === trim($line)) {
                continue;
            }

            $parts = explode(':', $line, 2);
            if (2 !== count($parts)) {
                continue;
            }

            $name = strtolower($parts[0]);
            $value = trim($parts[1]);
            $result[$name] = $value;
        }

        return $result;
    }

    private function createAttachment(
        object $part,
        int $uid,
        string $partNumber,
        bool $isSinglePartMessage,
        FetchOptions $options
    ): Attachment {
        $filename = $this->resolveAttachmentName($part, $partNumber);
        $mimeType = $this->resolveMimeType($part);
        $contentId = isset($part->id) ? trim((string) $part->id, '<>') : null;
        $size = isset($part->bytes) ? (int) $part->bytes : null;
        $content = null;
        $loader = null;

        if ($options->shouldFetchAttachmentContent()) {
            $body = $this->fetchPartBody($uid, $partNumber, $isSinglePartMessage);
            if (null !== $body) {
                $content = $this->decodeBody($body, (int) ($part->encoding ?? ENC7BIT));
                $size = strlen($content);
            } else {
                $content = '';
                $size ??= 0;
            }
        } else {
            $loader = function () use ($uid, $partNumber, $isSinglePartMessage, $part): string {
                $body = $this->fetchPartBody($uid, $partNumber, $isSinglePartMessage);
                if (null === $body) {
                    return '';
                }

                return $this->decodeBody($body, (int) ($part->encoding ?? ENC7BIT));
            };
        }

        return new Attachment(
            $filename,
            $mimeType,
            $content,
            $this->isInlinePart($part),
            $contentId,
            $size,
            $partNumber,
            $loader
        );
    }
}
