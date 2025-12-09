# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-01-XX

### Added
- Initial release of php-ymap
- Low-level `ImapClient` for direct IMAP operations
- High-level `ImapService` with fluent API
- Support for PHP 8.1+
- Full message parsing (text/HTML bodies, attachments, headers)
- Flexible IMAP-level filtering (date ranges, read/unread, answered status)
- Post-fetch exclusion filters (sender patterns, subject patterns)
- Field selection to fetch only needed data
- Flag management (mark as read/unread/answered/unanswered)
- Array/config-driven setup option
- Connection testing helper (`ImapService::testConnection()`)
- Comprehensive README with examples
- Demo application showcasing YEH integration
- PHPStan level 8 compliance
- MIT License

[1.0.0]: https://github.com/yaijs/php-ymap/releases/tag/v1.0.0

---

## [Unreleased] - Future Enhancements

### 🎯 Planned for v1.1.0

#### Performance Improvements
- **Batch UID operations** - Process multiple UIDs in single IMAP calls instead of sequential fetching
- **Attachment streaming** - Optional file streaming for large attachments to avoid memory overhead
- **Connection pooling** - Reuse IMAP connections for repeated operations
- **Configurable timeouts** - Per-operation timeout settings for slow IMAP servers
- **UID chunking** - Process large result sets in batches (e.g., 100 UIDs at a time)

#### Enhanced Features
- **Pagination offset** - Add `offset(int $offset)` method for batch fetching in large inboxes
- **Additional IMAP criteria** - Support for `LARGER`, `SMALLER`, `KEYWORD` filters
- **Message deletion/moving** - Extend beyond flag manipulation to mailbox operations
- **Folder management** - List, create, rename, delete mailboxes/folders
- **Multipart depth limit** - Prevent stack overflow on deeply nested MIME structures
- **Auto-reconnection** - Automatic reconnection logic for long-running processes

#### Developer Experience
- **PSR-3 logging interface** - Optional logger injection for debugging connection/parsing issues
- **PHPUnit test coverage** - Comprehensive test suite with mocked IMAP streams
- **Attachment content in API** - Optional base64-encoded attachment content in JSON responses
- **Additional flag support** - Deleted, flagged, draft flags for spam/priority marking
- **Timezone handling** - Explicit timezone support in date parsing
- **Enhanced validation** - Input validation for all config options (e.g., positive limits)

#### Code Quality
- **Magic number reduction** - Constants for all IMAP flags (e.g., `const FLAG_SEEN = 'SEEN'`)
- **Stricter array types** - Use union types (`string|int|bool`) instead of `mixed` where possible
- **Error context** - Add UID property to `MessageFetchException` for programmatic access
- **Field name validation** - Validate field names against `AVAILABLE_FIELDS` and throw exceptions

#### Security & Production
- **Rate limiting** - Built-in rate limiting for API endpoints
- **Configurable error behavior** - Option to log/throw on individual message fetch failures instead of silent skip
- **Enhanced input sanitization** - Review and strengthen `addslashes()` usage for edge cases

### 📋 Under Consideration

#### Community Requests
- **SMTP integration** - Companion library for sending emails (separate package)
- **OAuth2 support** - Modern authentication for Gmail/Outlook
- **Message search indexing** - Local search cache for faster repeated queries
- **Webhook support** - Push notifications for new messages
- **Multi-account management** - Handle multiple IMAP connections simultaneously

#### Advanced Features
- **S/MIME support** - Encrypted/signed email handling
- **Calendar attachments** - Parse `.ics` files for meeting invites
- **Advanced MIME parsing** - Handle edge cases in malformed emails
- **Export functionality** - Export messages to `.eml` or `.mbox` formats

---

### 🤝 Contributing Ideas

Have a feature request? Open an issue on GitHub or submit a PR!

**Priority areas for community contributions:**
1. Test coverage and edge case handling
2. Additional IMAP server compatibility testing
3. Performance benchmarks and optimizations
4. Documentation improvements and examples
5. Integration guides for popular frameworks (Laravel, Symfony, Shopware)

---

### 📝 Version History Notes

**Semantic Versioning Guide:**
- **Major (2.0.0)** - Breaking API changes
- **Minor (1.x.0)** - New features, backward compatible
- **Patch (1.0.x)** - Bug fixes, backward compatible
