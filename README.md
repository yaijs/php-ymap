# php-ymap

A lightweight fluent IMAP client for PHP 8.1+. Decode bodies, attachments, and headers, filter in one call, toggle flags, and preview everything via the included UI demo.

## Table of Contents

1. [Features](#features)
2. [Requirements](#requirements)
3. [Installation](#installation)
4. [Usage](#usage)
   - [Low-Level Client](#low-level-client)
   - [ImapService Fluent API](#imapservice-fluent-api)
   - [Array / Config-Driven Setup](#array--config-driven-setup)
   - [Connection Options](#connection-options)
5. [Field & Filter Reference](#field--filter-reference)
6. [Performance & Production Readiness](#performance--production-readiness)
   - [Real-World Benchmarks](#real-world-benchmarks)
   - [Memory Optimization with FetchOptions](#memory-optimization-with-fetchoptions)
   - [Shopware 6.7 Integration](#shopware-67-integration)
7. [Advanced Usage](#advanced-usage)
8. [Demo Application](#demo-application)
9. [Error Handling](#error-handling)
10. [Security](#security)
11. [Development & Testing](#development--testing)
12. [Contributing](#contributing)
13. [Troubleshooting](#troubleshooting)
14. [License](#license)

---

[![Packagist Version](https://img.shields.io/packagist/v/yaijs/php-ymap.svg)](https://packagist.org/packages/yaijs/php-ymap)
[![PHP Version Require](https://img.shields.io/packagist/php-v/yaijs/php-ymap.svg)](https://packagist.org/packages/yaijs/php-ymap)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat)](https://phpstan.org/)
[![AI Approved](https://img.shields.io/badge/AI%20Council-5%2F5%20Approved-success.svg?style=flat)](RELEASE_NOTES_v1.0.2.md)

> **🏆 v1.0.2 - The AI Council Approved Edition!** First IMAP library with unanimous approval from 5+ AI models (Grok 10/10, Gemini 3 Pro, Codex, DeepSeek, Claude). Now with connection abstraction layer (PHP 8.4 ready), memory-safe attachment streaming, and production benchmarks. [See what's new →](CHANGELOG.md#102---2025-12-19)

## Features

- 🔌 **Simple connection** – configure once with an array or chain fluent setters
- 📬 **Full message parsing** – text/HTML bodies, decoded attachments, cleaned headers
- 🔍 **Flexible filtering** – IMAP-level search plus post-fetch "exclude" filters
- 🎯 **Field selection** – fetch only what you need (UIDs, bodies, addresses, attachments…)
- ✉️ **Flag helpers** – mark messages read/unread/answered in a single call
- 🧱 **Encodings handled** – charset conversion and proper multipart parsing baked in
- 🖥️ **Demo UI** – modern HTML frontend for manual testing and QA
- 🚀 **Production-ready** – Memory-safe attachment streaming, tested on Gmail/ok.de/IONOS
- 🧪 **Testable** – Dependency injection support for mocking in unit tests

---

## Requirements

- PHP 8.1+
- Extensions: IMAP, mbstring, iconv, JSON
- Enable IMAP on Ubuntu/Debian: `sudo apt install php8.2-imap && sudo phpenmod imap`

---

## Installation

```bash
composer require yaijs/php-ymap
```

The package ships with PSR‑4 autoloading (`Yai\Ymap\*`) and no global functions.

---

## Usage

### Low-Level Client

```php
use Yai\Ymap\ConnectionConfig;
use Yai\Ymap\ImapClient;

$config = new ConnectionConfig(
    '{imap.gmail.com:993/imap/ssl}INBOX',
    'user@example.com',
    'app-password'
);

$client = new ImapClient($config);
$client->connect();

foreach ($client->getUnreadUids() as $uid) {
    $message = $client->fetchMessage($uid);
    echo $message->getSubject();
    $client->markAsRead($uid);
}
```

### ImapService Fluent API

```php
use Yai\Ymap\ImapService;

$messages = ImapService::create()
    ->connect('{imap.gmail.com:993/imap/ssl}INBOX', 'user@example.com', 'app-password')
    ->fields(['uid', 'subject', 'from', 'date', 'textBody'])
    ->includeAttachmentContent(false) // enable later if you need binary payloads
    ->since('2024-01-01')
    ->unreadOnly()
    ->excludeFrom(['noreply@', 'newsletter@'])
    ->limit(20)
    ->orderBy('desc')
    ->getMessages();

foreach ($messages as $msg) {
    echo "{$msg['subject']} from {$msg['from'][0]['email']}\n";
}
```

### Array / Config-Driven Setup

```php
use Yai\Ymap\ImapService;

$imap = new ImapService([
    'connection' => [
        'mailbox' => '{imap.gmail.com:993/imap/ssl}INBOX',
        'username' => 'user@example.com',
        'password' => 'app-password',
        'options' => 0,
        'retries' => 3,
        'parameters' => [
            'DISABLE_AUTHENTICATOR' => 'GSSAPI',
        ],
    ],
    'fields' => ['uid', 'subject', 'from', 'date', 'textBody'],
    'filters' => [
        'limit' => 10,
        'since' => '2024-01-01',
        'unread' => true,
    ],
    'exclude' => [
        'from' => ['noreply@', 'newsletter@'],
        'subject_contains' => ['Unsubscribe', 'Digest'],
    ],
]);

$messages = $imap->getMessages();
```

### Connection Options

`ImapService::connect()` (and the `connection` config section) accept the same parameters that PHP’s `imap_open()` does:

| Option | Description |
|--------|-------------|
| `mailbox` | IMAP path, e.g. `{imap.gmail.com:993/imap/ssl}INBOX` |
| `username`, `password` | Credentials or app password |
| `options` | Bitmask passed to `imap_open()` |
| `retries` | Retry count for `imap_open()` |
| `parameters` | Associative array passed to `imap_open()` (set TLS context, disable authenticators, etc.) |
| `encoding` | Target encoding for decoded bodies (default `UTF-8`) |

Need a lightweight “Test Credentials” button? Call the static helper:

```php
use Yai\Ymap\ImapService;
use Yai\Ymap\Exceptions\ConnectionException;

try {
    ImapService::testConnection(
        '{imap.gmail.com:993/imap/ssl}INBOX',
        'user@example.com',
        'app-password'
    );
    echo 'Connection OK!';
} catch (ConnectionException $e) {
    echo 'Failed: ' . $e->getMessage();
}
```

---

## Field & Filter Reference

### Available Fields

| Field | Description |
|-------|-------------|
| `uid` | Message UID (always included) |
| `subject` | Decoded subject |
| `date`, `dateRaw` | Formatted string (`Y-m-d H:i:s`) or original `DateTimeImmutable\|null` |
| `from`, `to`, `cc`, `bcc`, `replyTo` | Address arrays (`email` + optional `name`) |
| `textBody`, `htmlBody` | Plain text and HTML bodies (decoded, concatenated per part) |
| `preview` | Plain text summary (auto-generated from text or stripped HTML) |
| `attachments` | Filename, MIME type, size (inline + regular attachments) |
| `headers` | Normalized header map |
| `seen`, `answered` | Boolean flags mirrored from IMAP |
| `size` | Total message size (bytes) |

Use `fields([...])` and/or `excludeFields([...])` to tailor responses. `uid` is injected automatically.

**Note on Attachments:** The `attachments` field returns metadata by default. Payloads are decoded lazily to keep memory usage small, and you can opt into array payloads with `includeAttachmentContent()` or stream the bytes straight to disk with `ImapClient::saveAttachmentTo()`.

### Filter Methods

| Method | IMAP Criteria |
|--------|---------------|
| `since($date)` | `SINCE` |
| `before($date)` | `BEFORE` (inclusive) |
| `unreadOnly()` / `readOnly()` | `UNSEEN` / `SEEN` |
| `from($email)` / `to($email)` | `FROM` / `TO` |
| `subjectContains($text)` | `SUBJECT` |
| `bodyContains($text)` | `BODY` |
| `limit($n)`, `orderBy('asc'|'desc')` | Result shaping |
| `answeredOnly()`, `unansweredOnly()` | `ANSWERED` / `UNANSWERED` |

Post-fetch exclusions (evaluated after message parsing) help drop noisy senders or subjects:

```php
$imap->excludeFrom(['noreply@', 'quora.com'])
     ->excludeSubjectContains(['Unsubscribe', 'Digest']);
```

### Flag Helpers

```php
$imap->markAsRead([1234, 1235]);
$imap->markAsUnread(1236);
$imap->markAsAnswered(1237);
$imap->markAsUnanswered(1238);
```

Under the hood this proxies to `imap_setflag_full()` / `imap_clearflag_full()` using UIDs.

---

## Performance & Production Readiness

php-ymap has been tested in production environments and optimized for enterprise use, including message queue workers and scheduled tasks.

### Real-World Benchmarks

Performance tested across three production IMAP servers:

| Provider | 10 msgs | 25 msgs | 50 msgs | 100 msgs | Avg/msg |
|----------|---------|---------|---------|----------|---------|
| **ok.de** | 1.05s | 2.25s | 4.65s | 7.79s | ~105ms |
| **IONOS** | 2.30s | 5.83s | 12.57s | - | ~230ms |
| **Gmail** | 3.43s | 6.12s | 11.86s | 22.62s | ~226ms |

**Key Takeaways:**
- Linear scaling up to 100 messages
- Handles 18MB+ datasets efficiently
- Suitable for scheduled tasks and background processing
- Memory-safe with proper `FetchOptions` configuration

### Memory Optimization with FetchOptions

Control exactly what gets loaded into memory to prevent exhaustion in long-running processes:

```php
use Yai\Ymap\FetchOptions;

// Lightweight: Only metadata for inbox listings
$options = new FetchOptions(
    includeTextBody: false,
    includeHtmlBody: false,
    includeAttachmentMetadata: true,
    includeAttachmentContent: false  // ← Critical for large attachments!
);

$messages = $service->getMessages($options);
```

**Performance Impact:**
- 60-80% reduction in memory usage for list views
- Prevents memory exhaustion with 50MB+ attachments
- Ideal for scheduled tasks processing hundreds of emails

### Plugin Integration Example

php-ymap is designed for seamless integration with modern PHP frameworks and DI containers:

```php
// In your service configuration (e.g., services.xml, services.yaml)
<service id="YourApp\Service\EmailProcessorService">
    <argument type="service" id="Yai\Ymap\ImapService"/>
</service>

// In your scheduled task or background job
class EmailProcessorTask
{
    public function run(): void
    {
        $messages = $this->imapService
            ->fields(['uid', 'subject', 'from', 'preview'])
            ->limit(20)  // Process in batches
            ->unreadOnly()
            ->getMessages();

        foreach ($messages as $msg) {
            // Process message...
            $this->imapService->markAsRead($msg['uid']);
        }
    }
}
```

**Best Practices for Background Processing:**
1. Use `limit()` to process emails in batches (recommend 20-50)
2. Always set `includeAttachmentContent: false` unless needed
3. Use `saveAttachmentTo()` for files larger than 5MB
4. Register `ImapService` in DI container, not static calls
5. Handle `ConnectionException` gracefully to avoid task crashes

---

## Advanced Usage

### Working with Attachment Content

Attachments are decoded lazily so you only pay for what you touch. You can still grab bytes directly or stream them to disk without ever holding them in memory:

```php
use Yai\Ymap\ImapClient;
use Yai\Ymap\ConnectionConfig;

$config = new ConnectionConfig(
    '{imap.gmail.com:993/imap/ssl}INBOX',
    'user@example.com',
    'app-password'
);

$client = new ImapClient($config);
$client->connect();

$message = $client->fetchMessage(12345);

foreach ($message->getAttachments() as $attachment) {
    // Stream directly to the filesystem (no giant strings in memory)
    $client->saveAttachmentTo(
        $message->getUid(),
        $attachment,
        '/tmp/' . $attachment->getFilename()
    );

    // Or access the content lazily
    if ($attachment->getMimeType() === 'application/pdf') {
        processPdf($attachment->getContent());
    }

    if ($attachment->isInline()) {
        $contentId = $attachment->getContentId(); // For referencing in HTML
    }
}
```

### Including Attachment Content in JSON APIs

Opt-in when you truly need the payload:

```php
$messages = ImapService::create()
    ->connect('{imap.gmail.com:993/imap/ssl}INBOX', 'user@example.com', 'app-password')
    ->fields(['uid', 'subject', 'attachments'])
    ->includeAttachmentContent(true) // base64 by default
    ->getMessages();

foreach ($messages as $msg) {
    foreach ($msg['attachments'] as $attachment) {
        $binary = base64_decode($attachment['content']);
        // …
    }
}
```

**Note:** Including attachment content in JSON responses can significantly increase response size. Enable it only when necessary or stream to disk with `saveAttachmentTo()` for very large files.

---

## Demo Application

Run the bundled dashboard to experiment with filters and see real responses:

```bash
cd php-ymap/example
php -S localhost:8000
# open http://localhost:8000
```

The frontend (built with [YEH](https://yaijs.github.io/yai/docs/yeh/)) posts to `get.php`, which uses `ImapService` exclusively. The JSON API is a good reference if you want to plug php-ymap into another UI.

---

### Dependency Injection & Testing

php-ymap is built with testability in mind. The connection layer is fully abstracted via `ImapConnectionInterface`, making it easy to mock for tests.

#### Mock Connection for Unit Tests

```php
use Yai\Ymap\ImapClient;
use Yai\Ymap\Connection\ImapConnectionInterface;

// Create a mock connection (PHPUnit example)
$mockConnection = $this->createMock(ImapConnectionInterface::class);
$mockConnection->method('search')
    ->willReturn([1, 2, 3]);

// Inject into ImapClient
$client = new ImapClient($config, connection: $mockConnection);

// Now $client->searchUIDs() returns mocked data
```

#### Swap IMAP Transport at Service Level

```php
use Yai\Ymap\ImapService;
use Yai\Ymap\ImapClientInterface;

$service = ImapService::create()
    ->connect('{imap.host:993/imap/ssl}INBOX', 'user@example.com', 'secret')
    ->useClient($container->get(ImapClientInterface::class));
```

You can also call `withClientFactory()` to inject a factory that builds clients per connection config.

#### Future-Proof for PHP 8.4+

The `ImapConnectionInterface` abstraction prepares php-ymap for PHP 8.4, when `ext-imap` moves to PECL:

```php
use Yai\Ymap\Connection\ExtImapConnection;      // Current: wraps ext-imap
use Yai\Ymap\Connection\SocketImapConnection;   // Future: pure PHP (v2.0)

// v1.x: Uses ext-imap by default
$client = new ImapClient($config); // Uses ExtImapConnection

// v2.0: Auto-detect or manual override
$client = new ImapClient($config, connection: new SocketImapConnection());
```

**Current implementations:**
- `ExtImapConnection` - Wraps native PHP `imap_*` functions (default)
- Custom implementations welcome via `ImapConnectionInterface`

---

## Error Handling

```php
use Yai\Ymap\Exceptions\ConnectionException;
use Yai\Ymap\Exceptions\MessageFetchException;

try {
    $messages = $imap->getMessages();
} catch (ConnectionException $e) {
    // Invalid credentials, TLS failure, server unreachable, etc.
} catch (MessageFetchException $e) {
    // Individual message could not be parsed/fetched
}

// Optional: capture per-message failures instead of silently skipping
$imap->onError(static function (int $uid, \Throwable $e): void {
    error_log(sprintf('Failed to fetch UID %d: %s', $uid, $e->getMessage()));
});
```

`ImapService::disconnect()` lets you explicitly close the IMAP stream (`$imap->disconnect(true)` to expunge).

---

## Security

**Important:** Never hardcode IMAP credentials in your source code.

### Secure Credential Management

```php
use Yai\Ymap\ImapService;

// ✓ Good: Use environment variables
$messages = ImapService::create()
    ->connect(
        getenv('IMAP_MAILBOX'),
        getenv('IMAP_USER'),
        getenv('IMAP_PASS')
    )
    ->getMessages();

// ✗ Bad: Hardcoded credentials
$messages = ImapService::create()
    ->connect('{imap.gmail.com:993/imap/ssl}INBOX', 'user@example.com', 'password')
    ->getMessages();
```

### Secure Connections

Always use SSL/TLS when connecting over untrusted networks:

```php
// ✓ Good: SSL enabled
'{imap.gmail.com:993/imap/ssl}INBOX'

// ⚠️ Warning: Disables certificate validation (development only)
'{imap.example.com:993/imap/ssl/novalidate-cert}INBOX'
```

### Additional Security Practices

- **Limit result sets** to prevent resource exhaustion (`->limit(100)`)
- **Sanitize filenames** before saving attachments to disk (see example below)
- **Validate MIME types** when processing attachments
- **Implement rate limiting** for web-facing IMAP operations
- **Use field selection** to minimize data exposure (`->fields(['uid', 'subject'])`)
- **Stream large attachments** to prevent memory exhaustion attacks

### Secure Attachment Handling

Always sanitize attachment filenames to prevent path traversal attacks:

```php
function sanitizeFilename(string $filename): string {
    // Remove path traversal attempts
    $filename = basename($filename);

    // Remove dangerous characters
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

    // Prevent hidden files
    $filename = ltrim($filename, '.');

    return $filename ?: 'attachment.bin';
}

// Use it when saving attachments
foreach ($message->getAttachments() as $attachment) {
    $safeName = sanitizeFilename($attachment->getFilename());
    $client->saveAttachmentTo($message->getUid(), $attachment, "/secure/path/{$safeName}");
}
```

**Memory Safety:**
For attachments larger than your `memory_limit`, always use `saveAttachmentTo()` which streams directly to disk without loading into memory.

For detailed security guidelines, vulnerability reporting, and best practices, see [SECURITY.md](SECURITY.md).

---

## Development & Testing

```bash
composer install
./vendor/bin/phpstan analyse src/
# (optional) ./vendor/bin/phpunit
```

No additional tooling is required. PHPStan level is configured in `phpstan.neon`.

---

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines on:

- Code standards and style (PHP 8.1+, strict typing, PHPStan level 8)
- Pull request process
- What to contribute (bug fixes, docs, tests, performance improvements)
- How to report issues

For security vulnerabilities, please see our [Security Policy](SECURITY.md) instead of opening a public issue.

---

## Troubleshooting

| Issue | Hint |
|-------|------|
| “Can't connect to mailbox” | Double-check mailbox path, host firewall, and that the IMAP extension is enabled |
| Gmail authentication fails | Use an [App Password](https://support.google.com/accounts/answer/185833); basic auth is blocked |
| Empty `textBody` | Some emails are HTML-only – read `htmlBody` or strip tags yourself (see example app) |
| Self-signed certs | Provide stream context via `parameters` (e.g. `['DISABLE_AUTHENTICATOR' => 'PLAIN']`, or TLS context) |
| Extension missing | `sudo apt install php8.2-imap && sudo phpenmod imap` |

---

## License

MIT
