<?php declare(strict_types=1);

namespace Yai\Ymap;

use function array_key_exists;
use function array_merge;
use function date;
use function implode;
use function is_array;
use function strtotime;

/**
 * Configuration object for ImapService.
 * Handles parsing of array config and building IMAP search criteria.
 */
final class ServiceConfig
{
    // Connection settings
    public ?string $mailbox = null;
    public ?string $username = null;
    public ?string $password = null;
    public int $options = 0;
    public int $retries = 0;
    /** @var array<string, mixed> */
    public array $parameters = [];
    public string $encoding = 'UTF-8';

    // Field selection
    /** @var string[] */
    public array $fields = [];

    /** @var string[] */
    public array $excludeFields = [];

    // Filters (IMAP-level)
    public int $limit = 50;
    public string $order = 'desc';
    public ?string $since = null;
    public ?string $before = null;
    public ?bool $unread = null;
    public ?string $from = null;
    public ?string $to = null;
    public ?string $subjectContains = null;
    public ?string $bodyContains = null;
    public ?bool $answered = null;

    // Exclusions (Post-fetch filtering)
    /** @var string[] */
    public array $excludeFromPatterns = [];

    /** @var string[] */
    public array $excludeSubjectPatterns = [];

    /**
     * All available fields that can be returned.
     */
    public const AVAILABLE_FIELDS = [
        'uid',
        'subject',
        'date',
        'from',
        'to',
        'cc',
        'bcc',
        'replyTo',
        'textBody',
        'htmlBody',
        'attachments',
        'headers',
        'seen',
        'answered',
        'preview',
    ];

    /**
     * Default fields returned when none specified.
     */
    public const DEFAULT_FIELDS = [
        'uid',
        'subject',
        'date',
        'from',
        'textBody',
    ];

    /**
     * Create a ServiceConfig from an array configuration.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $instance = new self();

        // Connection settings
        if (isset($config['connection']) && is_array($config['connection'])) {
            $conn = $config['connection'];
            $instance->mailbox = $conn['mailbox'] ?? null;
            $instance->username = $conn['username'] ?? null;
            $instance->password = $conn['password'] ?? null;
            $instance->options = (int) ($conn['options'] ?? 0);
            $instance->retries = (int) ($conn['retries'] ?? 0);
            if (isset($conn['parameters']) && is_array($conn['parameters'])) {
                $instance->parameters = $conn['parameters'];
            }
            $instance->encoding = $conn['encoding'] ?? 'UTF-8';
        }

        // Field selection
        if (isset($config['fields']) && is_array($config['fields'])) {
            $instance->fields = $config['fields'];
        }
        if (isset($config['exclude_fields']) && is_array($config['exclude_fields'])) {
            $instance->excludeFields = $config['exclude_fields'];
        }

        // Filters
        if (isset($config['filters']) && is_array($config['filters'])) {
            $filters = $config['filters'];
            $instance->limit = (int) ($filters['limit'] ?? 50);
            $instance->order = ($filters['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
            $instance->since = $filters['since'] ?? null;
            $instance->before = $filters['before'] ?? null;

        if (array_key_exists('unread', $filters)) {
            $instance->unread = $filters['unread'];
        }
        if (array_key_exists('answered', $filters)) {
            $instance->answered = $filters['answered'];
        }

            $instance->from = $filters['from'] ?? null;
            $instance->to = $filters['to'] ?? null;
            $instance->subjectContains = $filters['subject_contains'] ?? null;
            $instance->bodyContains = $filters['body_contains'] ?? null;
        }

        // Exclusions
        if (isset($config['exclude']) && is_array($config['exclude'])) {
            $exclude = $config['exclude'];
            if (isset($exclude['from']) && is_array($exclude['from'])) {
                $instance->excludeFromPatterns = $exclude['from'];
            }
            if (isset($exclude['subject_contains']) && is_array($exclude['subject_contains'])) {
                $instance->excludeSubjectPatterns = $exclude['subject_contains'];
            }
        }

        return $instance;
    }

    /**
     * Merge override settings into a new config instance.
     *
     * @param array<string, mixed> $overrides
     */
    public function merge(array $overrides): self
    {
        $clone = clone $this;

        // Direct filter overrides
        if (isset($overrides['limit'])) {
            $clone->limit = (int) $overrides['limit'];
        }
        if (isset($overrides['order'])) {
            $clone->order = $overrides['order'] === 'asc' ? 'asc' : 'desc';
        }
        if (isset($overrides['since'])) {
            $clone->since = $overrides['since'];
        }
        if (isset($overrides['before'])) {
            $clone->before = $overrides['before'];
        }
        if (array_key_exists('unread', $overrides)) {
            $clone->unread = $overrides['unread'];
        }
        if (array_key_exists('answered', $overrides)) {
            $clone->answered = $overrides['answered'];
        }
        if (isset($overrides['from'])) {
            $clone->from = $overrides['from'];
        }
        if (isset($overrides['to'])) {
            $clone->to = $overrides['to'];
        }
        if (isset($overrides['subject_contains'])) {
            $clone->subjectContains = $overrides['subject_contains'];
        }
        if (isset($overrides['body_contains'])) {
            $clone->bodyContains = $overrides['body_contains'];
        }

        // Field overrides
        if (isset($overrides['fields']) && is_array($overrides['fields'])) {
            $clone->fields = $overrides['fields'];
        }
        if (isset($overrides['exclude_fields']) && is_array($overrides['exclude_fields'])) {
            $clone->excludeFields = $overrides['exclude_fields'];
        }

        // Exclusion overrides
        if (isset($overrides['exclude_from']) && is_array($overrides['exclude_from'])) {
            $clone->excludeFromPatterns = array_merge($clone->excludeFromPatterns, $overrides['exclude_from']);
        }
        if (isset($overrides['exclude_subject']) && is_array($overrides['exclude_subject'])) {
            $clone->excludeSubjectPatterns = array_merge($clone->excludeSubjectPatterns, $overrides['exclude_subject']);
        }

        return $clone;
    }

    /**
     * Build IMAP search criteria string from current filters.
     */
    public function toImapCriteria(): string
    {
        $criteria = [];

        if (null !== $this->since) {
            $timestamp = strtotime($this->since);
            if (false !== $timestamp) {
                $criteria[] = 'SINCE "' . date('j-M-Y', $timestamp) . '"';
            }
        }

        if (null !== $this->before) {
            $timestamp = strtotime($this->before);
            if (false !== $timestamp) {
                // Add one day to make 'before' inclusive of the end date
                $criteria[] = 'BEFORE "' . date('j-M-Y', $timestamp + 86400) . '"';
            }
        }

        if (true === $this->unread) {
            $criteria[] = 'UNSEEN';
        } elseif (false === $this->unread) {
            $criteria[] = 'SEEN';
        }

        if (true === $this->answered) {
            $criteria[] = 'ANSWERED';
        } elseif (false === $this->answered) {
            $criteria[] = 'UNANSWERED';
        }

        if (null !== $this->from && '' !== $this->from) {
            $criteria[] = 'FROM "' . addslashes($this->from) . '"';
        }

        if (null !== $this->to && '' !== $this->to) {
            $criteria[] = 'TO "' . addslashes($this->to) . '"';
        }

        if (null !== $this->subjectContains && '' !== $this->subjectContains) {
            $criteria[] = 'SUBJECT "' . addslashes($this->subjectContains) . '"';
        }

        if (null !== $this->bodyContains && '' !== $this->bodyContains) {
            $criteria[] = 'BODY "' . addslashes($this->bodyContains) . '"';
        }

        return [] === $criteria ? 'ALL' : implode(' ', $criteria);
    }

    /**
     * Get the list of fields to include in results.
     *
     * @return string[]
     */
    public function getActiveFields(): array
    {
        $fields = [] !== $this->fields ? $this->fields : self::DEFAULT_FIELDS;

        if ([] !== $this->excludeFields) {
            $fields = array_diff($fields, $this->excludeFields);
        }

        // Always include uid
        if (!in_array('uid', $fields, true)) {
            array_unshift($fields, 'uid');
        }

        return array_values($fields);
    }
}
