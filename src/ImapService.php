<?php declare(strict_types=1);

namespace Yai\Ymap;

use InvalidArgumentException;
use Yai\Ymap\Connection\ExtImapConnection;
use Yai\Ymap\Connection\ImapConnectionInterface;
use Yai\Ymap\Exceptions\ConnectionException;
use Yai\Ymap\Exceptions\MessageFetchException;
use function array_reverse;
use function array_slice;
use function count;
use function base64_encode;
use function mb_stripos;

/**
 * Developer-friendly facade for IMAP operations.
 * Wraps ImapClient with simplified configuration and fluent API.
 */
final class ImapService
{
    private ServiceConfig $config;

    private ?ImapClientInterface $client = null;

    private ?ImapConnectionInterface $connection = null;

    /** @var callable */
    private $clientFactory;

    /** @var callable|null */
    private $errorHandler = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = ServiceConfig::fromArray($config);
        $this->clientFactory = function (ConnectionConfig $connectionConfig, string $encoding): ImapClientInterface {
            return new ImapClient($connectionConfig, $encoding, $this->connection);
        };
    }

    /**
     * Named constructor for fluent chaining.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Quickly verify IMAP credentials without building service config.
     *
     * @param array<string, mixed> $parameters Optional IMAP parameters
     *
     * @throws ConnectionException
     */
    public static function testConnection(
        string $mailbox,
        string $username,
        string $password,
        int $options = 0,
        int $retries = 0,
        array $parameters = [],
        ?ImapConnectionInterface $connection = null
    ): bool {
        $config = new ConnectionConfig($mailbox, $username, $password, $options, $retries, $parameters);
        $client = new ImapClient($config, 'UTF-8', $connection);

        try {
            $client->connect();

            return true;
        } finally {
            $client->disconnect();
        }
    }

    /**
     * Set connection details.
     *
     * @param array<string, mixed> $parameters Optional IMAP parameters
     */
    public function connect(
        string $mailbox,
        string $username,
        string $password,
        int $options = 0,
        int $retries = 0,
        array $parameters = []
    ): self {
        $this->config->mailbox = $mailbox;
        $this->config->username = $username;
        $this->config->password = $password;
        $this->config->options = $options;
        $this->config->retries = $retries;
        $this->config->parameters = $parameters;

        return $this;
    }

    /**
     * Set target character encoding.
     */
    public function encoding(string $encoding): self
    {
        $this->config->encoding = $encoding;

        return $this;
    }

    /**
     * Set which fields to include in results.
     *
     * @param string[] $fields
     */
    public function fields(array $fields): self
    {
        $this->config->setFields($fields);

        return $this;
    }

    /**
     * Set which fields to exclude from results.
     *
     * @param string[] $fields
     */
    public function excludeFields(array $fields): self
    {
        $this->config->setExcludeFields($fields);

        return $this;
    }

    /**
     * Set maximum number of messages to return.
     */
    public function limit(int $count): self
    {
        $this->config->limit = $count;

        return $this;
    }

    /**
     * Set sort order ('asc' or 'desc').
     */
    public function orderBy(string $direction): self
    {
        $this->config->order = $direction === 'asc' ? 'asc' : 'desc';

        return $this;
    }

    /**
     * Filter messages since a date.
     */
    public function since(string $date): self
    {
        $this->config->since = $date;

        return $this;
    }

    /**
     * Filter messages before a date.
     */
    public function before(string $date): self
    {
        $this->config->before = $date;

        return $this;
    }

    /**
     * Filter to unread messages only.
     */
    public function unreadOnly(bool $flag = true): self
    {
        $this->config->unread = $flag ? true : null;

        return $this;
    }

    /**
     * Filter to read messages only.
     */
    public function readOnly(bool $flag = true): self
    {
        $this->config->unread = $flag ? false : null;

        return $this;
    }

    /**
     * Filter by sender email.
     */
    public function from(string $email): self
    {
        $this->config->from = $email;

        return $this;
    }

    /**
     * Filter by recipient email.
     */
    public function to(string $email): self
    {
        $this->config->to = $email;

        return $this;
    }

    /**
     * Filter by subject containing text.
     */
    public function subjectContains(string $text): self
    {
        $this->config->subjectContains = $text;

        return $this;
    }

    /**
     * Filter by body containing text.
     */
    public function bodyContains(string $text): self
    {
        $this->config->bodyContains = $text;

        return $this;
    }

    /**
     * Filter to answered messages only.
     */
    public function answeredOnly(bool $flag = true): self
    {
        $this->config->answered = $flag ? true : null;

        return $this;
    }

    /**
     * Filter to unanswered messages only.
     */
    public function unansweredOnly(bool $flag = true): self
    {
        $this->config->answered = $flag ? false : null;

        return $this;
    }

    /**
     * Exclude messages from specific senders (pattern matching).
     *
     * @param string[] $patterns
     */
    public function excludeFrom(array $patterns): self
    {
        $this->config->excludeFromPatterns = $patterns;

        return $this;
    }

    /**
     * Exclude messages with subjects containing specific text.
     *
     * @param string[] $patterns
     */
    public function excludeSubjectContains(array $patterns): self
    {
        $this->config->excludeSubjectPatterns = $patterns;

        return $this;
    }

    /**
     * Control whether the attachments array should include content payloads (base64 encoded).
     */
    public function includeAttachmentContent(bool $flag = true, string $encoding = 'base64'): self
    {
        $encoding = strtolower($encoding);
        if (!in_array($encoding, ['base64', 'binary'], true)) {
            throw new InvalidArgumentException('Attachment content encoding must be "base64" or "binary".');
        }

        $this->config->includeAttachmentContent = $flag;
        $this->config->attachmentContentEncoding = $encoding;

        return $this;
    }

    /**
     * Provide a shared IMAP client instance (useful for dependency injection and testing).
     */
    public function useClient(ImapClientInterface $client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Force the service to use a specific IMAP connector implementation.
     */
    public function useConnection(ImapConnectionInterface $connection): self
    {
        $this->connection = $connection;
        $this->client = null;

        return $this;
    }

    /**
     * Convenience helper to explicitly switch back to the ext-imap connector.
     */
    public function useExtImap(): self
    {
        return $this->useConnection(new ExtImapConnection());
    }

    /**
     * Override the factory responsible for creating ImapClient instances.
     *
     * @param callable(ConnectionConfig, string):ImapClientInterface $factory
     */
    public function withClientFactory(callable $factory): self
    {
        $this->clientFactory = $factory;

        return $this;
    }

    /**
     * Register an error handler invoked when individual messages fail to fetch.
     *
     * @param callable(int, \Throwable):void $handler
     */
    public function onError(callable $handler): self
    {
        $this->errorHandler = $handler;

        return $this;
    }

    /**
     * Fetch messages with optional runtime overrides.
     *
     * @param array<string, mixed> $overrides Runtime filter overrides
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ConnectionException
     */
    public function getMessages(array $overrides = []): array
    {
        $config = [] !== $overrides ? $this->config->merge($overrides) : $this->config;

        $client = $this->getClient();
        $criteria = $config->toImapCriteria();
        $uids = $client->search($criteria);

        // Apply order
        if ('desc' === $config->order) {
            $uids = array_reverse($uids);
        }

        // Apply limit
        $uidsToFetch = array_slice($uids, 0, $config->limit);

        $activeFields = $config->getActiveFields();
        $fetchOptions = $config->buildFetchOptions($activeFields);
        $messages = [];

        foreach ($uidsToFetch as $uid) {
            try {
                $message = $client->fetchMessage($uid, $fetchOptions);

                // Post-fetch exclusion filters
                if ($this->shouldExclude($message, $config)) {
                    continue;
                }

                $messages[] = $this->messageToArray($message, $activeFields, $config);
            } catch (MessageFetchException $e) {
                if (null !== $this->errorHandler) {
                    ($this->errorHandler)($uid, $e);
                }

                continue;
            }
        }

        return $messages;
    }

    /**
     * Fetch a single message by UID.
     *
     * @return array<string, mixed>|null
     *
     * @throws ConnectionException
     */
    public function getMessage(int $uid): ?array
    {
        $client = $this->getClient();

        try {
            $fetchOptions = $this->config->buildFetchOptions($this->config->getActiveFields());
            $message = $client->fetchMessage($uid, $fetchOptions);
            $activeFields = $this->config->getActiveFields();

            return $this->messageToArray($message, $activeFields, $this->config);
        } catch (MessageFetchException $e) {
            if (null !== $this->errorHandler) {
                ($this->errorHandler)($uid, $e);
            }

            return null;
        }
    }

    /**
     * Get count of unread messages.
     *
     * @throws ConnectionException
     */
    public function getUnreadCount(): int
    {
        $client = $this->getClient();

        return count($client->getUnreadUids());
    }

    /**
     * Get total message count with optional criteria.
     *
     * @throws ConnectionException
     */
    public function getTotalCount(string $criteria = 'ALL'): int
    {
        $client = $this->getClient();

        return count($client->search($criteria));
    }

    /**
     * Mark messages as read.
     *
     * @param int|int[] $uids
     *
     * @throws ConnectionException
     */
    public function markAsRead(int|array $uids): self
    {
        $this->getClient()->markAsRead($uids);

        return $this;
    }

    /**
     * Mark messages as unread.
     *
     * @param int|int[] $uids
     *
     * @throws ConnectionException
     */
    public function markAsUnread(int|array $uids): self
    {
        $this->getClient()->markAsUnread($uids);

        return $this;
    }

    /**
     * Mark messages as answered.
     *
     * @param int|int[] $uids
     *
     * @throws ConnectionException
     */
    public function markAsAnswered(int|array $uids): self
    {
        $this->getClient()->markAsAnswered($uids);

        return $this;
    }

    /**
     * Mark messages as unanswered.
     *
     * @param int|int[] $uids
     *
     * @throws ConnectionException
     */
    public function markAsUnanswered(int|array $uids): self
    {
        $this->getClient()->markAsUnanswered($uids);

        return $this;
    }

    /**
     * Explicitly disconnect from the IMAP server.
     */
    public function disconnect(bool $expunge = false): self
    {
        if (null !== $this->client) {
            $this->client->disconnect($expunge);
            $this->client = null;
        }

        return $this;
    }

    /**
     * Get the current config (for debugging/inspection).
     */
    public function getConfig(): ServiceConfig
    {
        return $this->config;
    }

    /**
     * Get or create the IMAP client.
     *
     * @throws ConnectionException
     */
    private function getClient(): ImapClientInterface
    {
        if (null !== $this->client) {
            return $this->client;
        }

        if (null === $this->config->mailbox || null === $this->config->username || null === $this->config->password) {
            throw new ConnectionException('Connection not configured. Use connect() or provide connection config.');
        }

        $connectionConfig = new ConnectionConfig(
            $this->config->mailbox,
            $this->config->username,
            $this->config->password,
            $this->config->options,
            $this->config->retries,
            $this->config->parameters
        );

        /** @var ImapClientInterface $client */
        $client = ($this->clientFactory)($connectionConfig, $this->config->encoding);
        $client->connect();
        $this->client = $client;

        return $client;
    }

    /**
     * Check if a message should be excluded based on post-fetch filters.
     */
    private function shouldExclude(Message $message, ServiceConfig $config): bool
    {
        // Check from patterns
        if ([] !== $config->excludeFromPatterns) {
            foreach ($message->getFrom() as $address) {
                $email = $address->getAddress();
                foreach ($config->excludeFromPatterns as $pattern) {
                    if (false !== mb_stripos($email, $pattern)) {
                        return true;
                    }
                }
            }
        }

        // Check subject patterns
        if ([] !== $config->excludeSubjectPatterns) {
            $subject = $message->getSubject() ?? '';
            foreach ($config->excludeSubjectPatterns as $pattern) {
                if (false !== mb_stripos($subject, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Convert a Message object to an array with selected fields.
     *
     * @param string[] $fields
     *
     * @return array<string, mixed>
     */
    private function messageToArray(Message $message, array $fields, ServiceConfig $config): array
    {
        $result = [];

        foreach ($fields as $field) {
            $result[$field] = match ($field) {
                'uid' => $message->getUid(),
                'subject' => $message->getSubject() ?? '',
                'date' => $message->getDate()?->format('Y-m-d H:i:s'),
                'dateRaw' => $message->getDate(),
                'from' => $this->addressesToArray($message->getFrom()),
                'to' => $this->addressesToArray($message->getTo()),
                'cc' => $this->addressesToArray($message->getCc()),
                'bcc' => $this->addressesToArray($message->getBcc()),
                'replyTo' => $this->addressesToArray($message->getReplyTo()),
                'textBody' => $message->getTextBody(),
                'htmlBody' => $message->getHtmlBody(),
                'attachments' => $this->attachmentsToArray($message->getAttachments(), $config),
                'headers' => $message->getHeaders(),
                'seen' => $message->isSeen(),
                'answered' => $message->isAnswered(),
                'size' => $message->getSize(),
                'preview' => $message->getPreviewBody(),
                default => null,
            };
        }

        return $result;
    }

    /**
     * Convert MessageAddress array to simple arrays.
     *
     * @param MessageAddress[] $addresses
     *
     * @return array<int, array{email: string, name: string|null}>
     */
    private function addressesToArray(array $addresses): array
    {
        $result = [];

        foreach ($addresses as $address) {
            $result[] = [
                'email' => $address->getAddress(),
                'name' => $address->getName(),
            ];
        }

        return $result;
    }

    /**
     * Convert Attachment array to simple arrays.
     *
     * @param Attachment[] $attachments
     *
     * @return array<int, array{filename: string, size: int, mimeType: string, isInline: bool}>
     */
    private function attachmentsToArray(array $attachments, ServiceConfig $config): array
    {
        $result = [];
        $includeContent = $config->includeAttachmentContent;

        foreach ($attachments as $attachment) {
            $entry = [
                'filename' => $attachment->getFilename(),
                'size' => $attachment->getSize(),
                'mimeType' => $attachment->getMimeType(),
                'isInline' => $attachment->isInline(),
            ];

            if ($includeContent) {
                $binary = $attachment->getContent();
                $entry['content'] = 'binary' === $config->attachmentContentEncoding
                    ? $binary
                    : base64_encode($binary);
            }

            $result[] = $entry;
        }

        return $result;
    }
}
