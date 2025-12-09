<?php declare(strict_types=1);

/**
 * IMAP API Endpoint - Powered by ImapService
 */

use Yai\Ymap\ImapService;
use Yai\Ymap\Exceptions\ConnectionException;

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json');

require_once dirname(__DIR__) . '/vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$messageUid = isset($_GET['message']) ? (int) $_GET['message'] : null;
if (null !== $messageUid && $messageUid <= 0) {
    $messageUid = null;
}
$messageAction = $_GET['action'] ?? null;

$bodyLength = (int) ($_POST['body_length'] ?? 500);
if ($bodyLength < 100) {
    $bodyLength = 100;
} elseif ($bodyLength > 20000) {
    $bodyLength = 20000;
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if (!$username || !$password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Username and password required']);
    exit;
}

try {
    $imap = ImapService::create()
        ->connect(
            $_POST['mailbox'] ?: '{imap.gmail.com:993/imap/ssl}INBOX',
            $username,
            $password
        )
        ->fields([
            'uid',
            'subject',
            'from',
            'to',
            'cc',
            'replyTo',
            'date',
            'textBody',
            'htmlBody',
            'attachments',
            'seen',
            'answered',
            'preview',
        ])
        ->limit((int) ($_POST['limit'] ?? 10))
        ->orderBy('desc');

    // Add optional filters
    if (!empty($_POST['date_from'])) {
        $imap->since($_POST['date_from']);
    }
    if (!empty($_POST['date_to'])) {
        $imap->before($_POST['date_to']);
    }
    if (($_POST['read_status'] ?? '') === 'UNREAD') {
        $imap->unreadOnly();
    } elseif (($_POST['read_status'] ?? '') === 'READ') {
        $imap->readOnly();
    }
    if (($_POST['answered_status'] ?? '') === 'ANSWERED') {
        $imap->answeredOnly();
    } elseif (($_POST['answered_status'] ?? '') === 'UNANSWERED') {
        $imap->unansweredOnly();
    }
    if (!empty($_POST['search_text'])) {
        match ($_POST['search_field'] ?? 'SUBJECT') {
            'FROM' => $imap->from($_POST['search_text']),
            'TO' => $imap->to($_POST['search_text']),
            'BODY', 'TEXT' => $imap->bodyContains($_POST['search_text']),
            default => $imap->subjectContains($_POST['search_text']),
        };
    }

    // Exclusion filters from textareas (one pattern per line)
    if (!empty($_POST['exclude_from'])) {
        $patterns = array_filter(array_map('trim', explode("\n", $_POST['exclude_from'])));
        if ($patterns) {
            $imap->excludeFrom($patterns);
        }
    }
    if (!empty($_POST['exclude_subject'])) {
        $patterns = array_filter(array_map('trim', explode("\n", $_POST['exclude_subject'])));
        if ($patterns) {
            $imap->excludeSubjectContains($patterns);
        }
    }

    $mapAddresses = static function (array $addresses): array {
        $result = [];

        foreach ($addresses as $entry) {
            if (is_array($entry)) {
                $result[] = [
                    'email' => (string) ($entry['email'] ?? ''),
                    'name' => $entry['name'] ?? null,
                ];
                continue;
            }

            $value = (string) $entry;
            if (preg_match('/^(.*)<([^>]+)>$/', $value, $matches)) {
                $result[] = [
                    'email' => trim($matches[2]),
                    'name' => trim($matches[1]) ?: null,
                ];
            } else {
                $result[] = [
                    'email' => $value,
                    'name' => null,
                ];
            }
        }

        return $result;
    };

    $formatMessage = static function (array $msg) use ($bodyLength, $mapAddresses) {
        // Use the built-in preview which handles text/HTML fallback and whitespace
        $body = $msg['preview'] ?? '';
        $bodyPreview = mb_substr($body, 0, $bodyLength);
        $isTruncated = mb_strlen($body) > $bodyLength;

        return [
            'uid' => $msg['uid'],
            'subject' => $msg['subject'] ?? '',
            'from' => $mapAddresses($msg['from'] ?? []),
            'to' => $mapAddresses($msg['to'] ?? []),
            'cc' => $mapAddresses($msg['cc'] ?? []),
            'replyTo' => $mapAddresses($msg['replyTo'] ?? []),
            'date' => $msg['date'] ? date('M j, Y H:i', strtotime($msg['date'])) : null,
            'bodyPreview' => $bodyPreview,
            'bodyFull' => $body,
            'bodyTruncated' => $isTruncated,
            'htmlBody' => $msg['htmlBody'] ?? null,
            'seen' => (bool) ($msg['seen'] ?? false),
            'answered' => (bool) ($msg['answered'] ?? false),
            'attachments' => array_map(static fn($a) => [
                'filename' => $a['filename'],
                'size' => $a['size'],
                'sizeFormatted' => number_format($a['size'] / 1024, 1) . ' KB',
            ], $msg['attachments'] ?? []),
        ];
    };

    if (null !== $messageUid) {
        $actionMap = [
            'mark-read' => 'markAsRead',
            'mark-unread' => 'markAsUnread',
            'mark-answered' => 'markAsAnswered',
            'mark-unanswered' => 'markAsUnanswered',
        ];

        if (null !== $messageAction) {
            if (!isset($actionMap[$messageAction])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid action requested']);
                exit;
            }

            $imap->{$actionMap[$messageAction]}($messageUid);
        }

        $singleMessage = $imap->getMessage($messageUid);
        if (null === $singleMessage) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Message not found']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => $formatMessage($singleMessage),
        ], JSON_INVALID_UTF8_SUBSTITUTE);

        exit;
    }

    $messages = $imap->getMessages();

    // Format for frontend
    $output = array_map($formatMessage, $messages);

    // Get total count matching criteria (without limit)
    $totalFound = $imap->getTotalCount($imap->getConfig()->toImapCriteria());

    echo json_encode([
        'success' => true,
        'count' => count($output),
        'totalFound' => $totalFound,
        'searchCriteria' => $imap->getConfig()->toImapCriteria(),
        'messages' => $output,
    ], JSON_INVALID_UTF8_SUBSTITUTE);

} catch (ConnectionException $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
