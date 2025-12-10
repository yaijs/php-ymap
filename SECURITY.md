# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

If you discover a security vulnerability in php-ymap, please report it privately:

**DO NOT open a public GitHub issue for security vulnerabilities.**

### How to Report

1. **Email:** Send details to the maintainers via GitHub (use the "Report a security vulnerability" feature in the Security tab)
2. **Include:**
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if available)

### What to Expect

- **Acknowledgment:** Within 48 hours
- **Initial assessment:** Within 1 week
- **Fix timeline:** Depends on severity and complexity
- **Credit:** You will be credited in the security advisory (unless you prefer to remain anonymous)

## Security Best Practices for Users

### Credential Management

**DO:**
- Store IMAP credentials in environment variables
- Use secure vaults (AWS Secrets Manager, HashiCorp Vault, etc.)
- Rotate credentials regularly
- Use application-specific passwords when available

**DON'T:**
- Hardcode credentials in source code
- Commit credentials to version control
- Log credentials in plain text
- Share credentials across multiple applications

### TLS/SSL Configuration

php-ymap connects to IMAP servers using the native PHP IMAP extension. Ensure secure connections:

```php
$config = new ConnectionConfig(
    host: 'imap.example.com',
    port: 993,              // Use SSL port
    username: getenv('IMAP_USER'),
    password: getenv('IMAP_PASS'),
    flags: '/imap/ssl',     // Enable SSL
    mailbox: 'INBOX'
);
```

**Flags for secure connections:**
- `/imap/ssl` - Use SSL/TLS encryption
- `/imap/ssl/novalidate-cert` - **Avoid in production** (disables certificate verification)

### Input Validation

When using php-ymap in web applications:

- **Sanitize user inputs** before using in IMAP searches
- **Validate email addresses** before using in filters
- **Limit result sets** to prevent resource exhaustion
- **Implement rate limiting** on IMAP operations

### Attachment Handling

When processing attachments:

```php
// Sanitize filenames before saving to disk
$filename = basename($attachment->getFilename());
$filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

// Validate file types
$allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
if (!in_array($attachment->getContentType(), $allowedTypes)) {
    // Reject or handle appropriately
}

// Limit file sizes
if ($attachment->getSize() > 10 * 1024 * 1024) { // 10MB
    // Reject large attachments
}
```

### Resource Limits

Prevent memory exhaustion:

```php
// Limit number of messages fetched
$messages = $service
    ->inbox()
    ->limit(100)  // Don't fetch unbounded result sets
    ->fetch();

// Use field selection to reduce memory usage
$messages = $service
    ->inbox()
    ->fields(['uid', 'subject', 'from', 'date'])  // Omit large bodies
    ->fetch();
```

## Known Security Considerations

1. **IMAP Extension:** php-ymap depends on PHP's native IMAP extension which uses the c-client library. Keep PHP updated to receive security patches.

2. **Memory Usage:** Large attachments are loaded into memory. For production use with large attachments, consider implementing streaming (see TASK_LIST.md).

3. **Connection Security:** Always use SSL/TLS for IMAP connections when connecting over untrusted networks.

## Disclosure Policy

When a security issue is fixed:

1. A security advisory will be published on GitHub
2. CHANGELOG.md will be updated with security fix details
3. A new patch version will be released
4. Affected versions will be clearly documented

## Security Updates

Subscribe to security advisories:
- Watch the GitHub repository for security alerts
- Check CHANGELOG.md for security-related fixes
