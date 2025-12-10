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
6. [Demo Application](#demo-application)
7. [Error Handling](#error-handling)
8. [Development & Testing](#development--testing)
9. [Troubleshooting](#troubleshooting)
10. [License](#license)

---

[![Packagist Version](https://img.shields.io/packagist/v/yaijs/php-ymap.svg)](https://packagist.org/packages/yaijs/php-ymap)
[![PHP Version Require](https://img.shields.io/packagist/php-v/yaijs/php-ymap.svg)](https://packagist.org/packages/yaijs/php-ymap)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat)](https://phpstan.org/)

## Features

- 🔌 **Simple connection** – configure once with an array or chain fluent setters
- 📬 **Full message parsing** – text/HTML bodies, decoded attachments, cleaned headers
- 🔍 **Flexible filtering** – IMAP-level search plus post-fetch “exclude” filters
- 🎯 **Field selection** – fetch only what you need (UIDs, bodies, addresses, attachments…)
- ✉️ **Flag helpers** – mark messages read/unread/answered in a single call
- 🧱 **Encodings handled** – charset conversion and proper multipart parsing baked in
- 🖥️ **Demo UI** – modern HTML frontend for manual testing and QA

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

Use `fields([...])` and/or `excludeFields([...])` to tailor responses. `uid` is injected automatically.

**Note on Attachments:** The `attachments` field returns metadata by default (filename, size, MIME type). Full binary content is automatically fetched and decoded, accessible via `$attachment->getContent()` when working with `Message` objects directly. For JSON APIs, you can include base64-encoded content if needed (see Advanced Usage below).

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

## Advanced Usage

### Working with Attachment Content

Attachments are automatically fetched and fully decoded. Access binary content directly:

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
    // Save attachment to disk
    file_put_contents(
        '/tmp/' . $attachment->getFilename(),
        $attachment->getContent()
    );

    // Or process directly
    if ($attachment->getMimeType() === 'application/pdf') {
        processPdf($attachment->getContent());
    }

    // Check if it's inline (embedded image)
    if ($attachment->isInline()) {
        $contentId = $attachment->getContentId(); // For referencing in HTML
    }
}
```

### Including Attachment Content in JSON APIs

For API responses, base64-encode the content:

```php
$messages = $imap->getMessages();

$formatted = array_map(function($msg) {
    return [
        'subject' => $msg['subject'],
        'attachments' => array_map(function($att) {
            return [
                'filename' => $att['filename'],
                'mimeType' => $att['mimeType'],
                'size' => $att['size'],
                'content' => base64_encode($att['content']), // Include binary content
            ];
        }, $msg['attachments']),
    ];
}, $messages);

echo json_encode($formatted);
```

**Note:** Including attachment content in JSON responses can significantly increase response size. Consider fetching attachments on-demand for large files.

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
```

`ImapService::disconnect()` lets you explicitly close the IMAP stream (`$imap->disconnect(true)` to expunge).

---

## Development & Testing

```bash
composer install
./vendor/bin/phpstan analyse src/
# (optional) ./vendor/bin/phpunit
```

No additional tooling is required. PHPStan level is configured in `phpstan.neon`.

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
