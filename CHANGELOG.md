# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.2] - 2025-12-19

### Added
- **Connection Abstraction Layer (PHP 8.4 Future-Proofing)**
  - `ImapConnectionInterface` - Abstraction layer for IMAP protocol operations
  - `ExtImapConnection` - Clean wrapper around native `imap_*` functions
  - Constructor injection support in `ImapClient` for custom connection implementations
  - Fully mockable architecture for unit testing
  - Paves the way for pure PHP/socket implementation in v2.0

- **Memory Optimization**
  - `FetchOptions` value object for granular control over what gets loaded
  - `saveAttachmentTo()` streaming method to save large attachments without memory loading
  - Lazy-loading support in `Attachment` class
  - Field-aware parsing - only fetch requested data

- **Testing Infrastructure**
  - `ServiceConfigTest.php` for configuration validation
  - Enhanced `AttachmentTest.php` with edge cases
  - GitHub Actions CI workflow for PHP 8.1/8.2/8.3
  - PHPUnit + PHPStan Level 8 validation

- **Documentation**
  - Performance & Production Readiness section with real-world benchmarks
  - Plugin Integration guide with best practices
  - Memory Optimization with FetchOptions examples
  - Secure Attachment Handling with sanitization code
  - Dependency Injection & Testing guide with mocking examples

### Changed
- **Architecture:** ImapClient now uses dependency injection for connection layer
- **Error Handling:** Improved error transparency for production debugging
- **Field Validation:** Strict validation of requested fields with helpful exceptions

### Performance
- **Real-world benchmarks** across Gmail, ok.de, and IONOS
  - ok.de: ~105ms per message
  - IONOS: ~230ms per message
  - Gmail: ~226ms per message
- **60-80% reduction** in memory usage for list views with FetchOptions
- **Linear scaling** up to 100 messages with 18MB+ datasets

### Security
- Path traversal protection guidelines
- Attachment filename sanitization examples
- Secure defaults in FetchOptions (attachment content off by default)

### Files Modified
- `src/Connection/ImapConnectionInterface.php` - New interface (15 methods)
- `src/Connection/ExtImapConnection.php` - New implementation
- `src/ImapClient.php` - Added connection injection support
- `src/FetchOptions.php` - New value object for fetch control
- `tests/ServiceConfigTest.php` - New test file
- `README.md` - Major documentation enhancements

[1.0.2]: https://github.com/yaijs/php-ymap/releases/tag/v1.0.2

## [1.0.1] - 2025-12-10

### Fixed
- **Critical:** Emails with `Content-Disposition: inline` on text/plain or text/html parts were incorrectly treated as attachments instead of body content
  - Affected newsletters and marketing emails (e.g., STRATO newsletters) that appeared empty with body parts listed as attachments
  - Modified `ImapClient::isAttachmentPart()` to check content type before disposition
  - Text parts with inline disposition are now correctly treated as body content

### Added
- Message size tracking and display
  - Individual message sizes shown for each message (content-based: text + HTML + attachments)
  - Total size counter in messages header with message count
  - Smart formatting (B/KB/MB units)
  - Typically 79-86% of RFC822 message size (excludes headers, MIME boundaries, encoding overhead)

### Changed
- **Demo improvements:**
  - Modal animations: Smooth slide-up/down transitions on open/close
  - Modal navigation: Previous/Next buttons for browsing messages without closing modal
  - Select elements: Fixed read/unread and answered/unanswered selects to use consistent option ordering
  - Initial state: Messages now arrive collapsed by default with synchronized toggle button

### Files Modified
- `src/ImapClient.php` - Fixed attachment detection logic, added size calculation
- `src/Message.php` - Added size property with getter/setter
- `src/ImapService.php` - Added 'size' field to serialization
- `src/ServiceConfig.php` - Added 'size' to DEFAULT_FIELDS
- `example/get.php` - Size formatting and field inclusion
- `example/index.php` - Modal UX enhancements, size display, collapsed state

[1.0.1]: https://github.com/yaijs/php-ymap/releases/tag/v1.0.1

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
5. Integration guides for popular frameworks (Laravel, Symfony, etc.)

---

### 📝 Version History Notes

**Semantic Versioning Guide:**
- **Major (2.0.0)** - Breaking API changes
- **Minor (1.x.0)** - New features, backward compatible
- **Patch (1.0.x)** - Bug fixes, backward compatible
