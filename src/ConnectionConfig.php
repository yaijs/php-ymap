<?php declare(strict_types=1);

namespace Yai\Ymap;

/**
 * Holds the IMAP mailbox path and credential details.
 */
final class ConnectionConfig
{
    private string $mailboxPath;

    private string $username;

    private string $password;

    private int $options;

    private int $retries;

    /**
     * @var array<string, mixed>
     */
    private array $parameters;

    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        string $mailboxPath,
        string $username,
        string $password,
        int $options = 0,
        int $retries = 0,
        array $parameters = []
    ) {
        $this->mailboxPath = $mailboxPath;
        $this->username = $username;
        $this->password = $password;
        $this->options = $options;
        $this->retries = $retries;
        $this->parameters = $parameters;
    }

    public function getMailboxPath(): string
    {
        return $this->mailboxPath;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getOptions(): int
    {
        return $this->options;
    }

    public function getRetries(): int
    {
        return $this->retries;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
