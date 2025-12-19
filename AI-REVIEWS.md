
---------------------------
--- Gemini 3 Pro Review ---
---------------------------

# Code Review & Recommendations

Here is a deep analysis of `yaijs/php-ymap` version 1.x, focusing on code quality, performance, and suitability for production environment.

### 🛡️ Critical Issues

> [!CAUTION]
> **Memory Usage with Large Attachments**
> The current implementation automatically downloads and decodes **all** attachments into memory when `fetchMessage()` is called.
> - **Risk:** If a user receives a 50MB email, the PHP process memory will spike significantly. In a scheduled task (Message Queue) processing multiple emails, this could lead to `Fatal Error: Allowed memory size exhausted`.
> - **Recommendation:** Implement a stream-based approach or `downloadAttachment($path)` method so attachments can be saved directly to the filesystem (or S3 stream) without holding the entire binary string in RAM.

### ⚠️ Major Issues

> [!WARNING]
> **Dependency on `ext-imap` (Future Proofing)**
> The library relies heavily on the native PHP IMAP extension (`imap_open`, etc.).
> - **Context:** PHP 8.4 deprecates `ext-imap` and moves it to PECL. It is no longer bundled by default in many modern PHP docker images (like minimal Alpine builds) without extra configuration.
> - **Recommendation:** Consider abstracting the connection layer to support a pure-PHP userland implementation (like `ddeboer/imap`'s socket logic) in a future 2.0 release.

> [!IMPORTANT]
> **Lack of Integration Testing**
> The `tests/` directory only contains unit tests for `Message` and entities.
> - **Gap:** There are NO tests for `ImapClient` or `ImapService`. The core logic (connection, fetching, parsing) is untested in CI.
> - **Risk:** Regressions in the connection logic or IMAP parsing will not be caught automatically.
> - **Recommendation:** Add integration tests. Since you cannot easily mock the `imap_*` functions (they are not objects), you should at least create an interface for `ImapClient` so apps can mock the library for their own tests.

### 🔍 Minor Issues & Observations

1.  **Tight Coupling in Facade**: `ImapService` directly instantiates `ImapClient`. It’s hard to swap out the client implementation (e.g., for a mock client during development).
    - *Fix:* Allow injecting `ImapClient` into `ImapService` constructor or add a `setClient()` method.
2.  **Error Handling**: usage of `@imap_open` is standard for this extension to suppress warnings, and you correctly check `false` and throw `ConnectionException`. This is good, but ensure `imap_errors()` is cleared to prevent leaking error stack into subsequent calls.
3.  **Strict Typing**: The library uses `declare(strict_types=1);` and proper type hints everywhere. **This is excellent** and aligns perfectly with modern standards.

### 💡 Suggestions for "Symfony" Usage

When integrating this into Symfony:

1.  **Dependency Injection**: Don't use `ImapService::create()` static calls inside your services. Register `ImapService` (or a factory) in your `services.xml` so you can configure it via Symfony System Config.
2.  **Scheduled Tasks**: If processing emails in a background task, ensure you use `limit()` (e.g., 20 at a time) and handle `ConnectionException` gracefully to avoid crashing the queue worker.
3.  **Attachment Handling**: If your system processes attachments, ensure you check file size *before* processing `attachment->getContent()`.


-----------------------------------
-----------------------------------
--- Codex AI Reviews THE REVIEW ---
-----------------------------------
-----------------------------------


# AI Review Resolution Tasks

## Critical (Gemini 3 Pro)

- [ ] **Stream or lazily load attachments instead of retaining full binaries in memory.**
  Source: Gemini 3 Pro review (memory usage alert). The current parser always decodes every attachment into an in-memory `Attachment` object during `ImapClient::fetchMessage()` (see `src/ImapClient.php:395-424`), so a single 50 MB message can exhaust memory in a Shopware scheduled task. Design a streaming or disk-backed API (e.g., `downloadAttachment($uid, $partNumber, $targetPath)` or lazy `AttachmentContentLoader`) and expose configuration so `ImapService`/consumers can opt-in without changing `getMessages()` signatures. Update docs, the example app, and add regression tests for large attachments.

- [ ] **Make body/attachment parsing aware of requested fields so unneeded parts are never fetched.**
  Even when callers request only `uid`, `subject`, and `preview`, `ImapClient::parseStructure()` still fetches and decodes HTML bodies and every attachment (`src/ImapClient.php:363-424`) because `ImapService` has no way to tell the client which fields are needed (`src/ImapService.php:496-574`). Introduce a field mask (or lightweight `FetchOptions` value object) that lets service/config specify whether to load text bodies, HTML, preview-only, attachment metadata, or attachment content. This reduces latency and memory usage for the Smart Mailbot pipeline.

## High Priority (Gemini 3 Pro & Copilot)

- [ ] **Decouple `ImapService` from the native `ext-imap` implementation.**
  Gemini highlighted that PHP 8.4 removes the bundled extension, and Copilot called out the lack of mocks. Allow injecting a custom `ImapClientInterface` (or at least an `ImapClientFactory`) so Shopware can swap in a userland client or mock when `ext-imap` is unavailable. Update `src/ImapService.php:18-463` to accept a pre-built client/factory, document the extension requirement in README, and pave the way for a PECL/userland fallback in v2.0.

- [ ] **Add real integration tests for connection, fetching, parsing, and flag toggling.**
  Both reviews noted that `tests/` only covers value objects (`AttachmentTest.php`, `MessageTest.php`). After introducing an interface/factory, add PHPUnit suites (or Pest) that exercise `ImapClient` & `ImapService` end-to-end using an IMAP fixture server or stubbed transport so regressions in search criteria, multipart parsing, attachment handling, and flag helpers are caught automatically.

- [ ] **Surface errors instead of silently dropping problematic messages.**
  `ImapService::getMessages()` catches `MessageFetchException` and just `continue`s with no logging (`src/ImapService.php:285-324`), making production debugging impossible. Add PSR-3 logger support or a user-supplied callback so Smart Mailbot can record which UID failed and why, and consider exposing a “partial failure” result rather than hiding errors.

## Medium Priority (Copilot)

- [ ] **Align attachment metadata with documentation.**
  README section “Including Attachment Content in JSON APIs” (`README.md:258-292`) claims `$msg['attachments'][]['content']` is available, but `attachmentsToArray()` only returns filename/size/MIME/isInline (`src/ImapService.php:553-574`). Either extend the payload (optionally gated to avoid large responses) to include sanitized/base64 content, or correct the README and mention how to fetch attachment bodies separately via `ImapClient`.

- [ ] **Validate requested fields and expose the complete list.**
  `ServiceConfig::AVAILABLE_FIELDS` omits fields that are actually emitted (`size`, `dateRaw`) and the library silently inserts `null` for unknown names (`src/ServiceConfig.php:62-90`, `src/ImapService.php:502-527`). Update the constants/docs, add validation with meaningful exceptions (helpful for Shopware system-config forms), and backfill tests/documentation.

- [ ] **Add continuous integration to run phpstan + phpunit.**
  Composer scripts (`composer.json`) reference `analyse` and `test`, but there is no `.github/workflows` pipeline. Add a GitHub Actions workflow that runs `composer validate`, `composer install`, PHPStan (level 8), and PHPUnit on PHP 8.1/8.3 to catch regressions before publishing to Packagist.

- [ ] **Provide attachment filename sanitization helpers.**
  Copilot flagged attachment handling. Right now `resolveAttachmentName()` trusts whatever filename the sender supplied (`src/ImapClient.php:509-517`), so saving via `Attachment::getFilename()` can allow path traversal. Add a helper (or configurable sanitizer) that strips dangerous characters and optionally exposes the original name separately, and document its use in README/example to keep Smart Mailbot’s filesystem interactions safe.



## A füw minüts leytör:


------------------------------
--- Gemini 3 Pro Re-Review ---
------------------------------


# Re-Review: Issues Resolved (v1.0.1+)

I have re-reviewed the codebase and confirmed that the critical and major issues identified previously have been **fully resolved**.

### ✅ Resolved Issues

1.  **Memory Usage / Streaming Attachments**
    -   **Fix Validated:** `ImapClient` now supports `saveAttachmentTo($uid, $attachment, $destination)` which uses `imap_savebody` to stream content directly to a file resource.
    -   **Optimization:** `FetchOptions` and `ServiceConfig::buildFetchOptions()` implementation ensures that attachment content is **not** loaded into memory unless explicitly requested. Lazy loading has also been implemented in `Attachment`.
    -   **Result:** Safe for use in Scheduled Tasks.

2.  **Architecture & Decoupling**
    -   **Fix Validated:** `ImapClientInterface` has been introduced.
    -   **Fix Validated:** `ImapService` now allows injecting a custom client via `useClient()` or `withClientFactory()`.
    -   **Result:** The library is now fully testable and mockable within any environment. `ext-imap` can be mocked out for unit tests.

3.  **Field Validation**
    -   **Fix Validated:** `ServiceConfig` now strictly validates requested fields against `AVAILABLE_FIELDS` and throws `InvalidArgumentException` for typos.

### 🚀 Production Readiness
The library is now **production-ready** for any use case. The strict typing, low-memory footprint options, and interface-driven design meet high-quality standards.



