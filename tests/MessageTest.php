<?php declare(strict_types=1);

namespace Yai\Ymap\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yai\Ymap\Attachment;
use Yai\Ymap\Message;
use Yai\Ymap\MessageAddress;

final class MessageTest extends TestCase
{
    public function testMessageCreationWithUid(): void
    {
        $message = new Message(12345);

        $this->assertSame(12345, $message->getUid());
    }

    public function testSubjectGetterAndSetter(): void
    {
        $message = new Message(1);

        $this->assertNull($message->getSubject());

        $message->setSubject('Test Subject');
        $this->assertSame('Test Subject', $message->getSubject());
    }

    public function testDateGetterAndSetter(): void
    {
        $message = new Message(1);
        $date = new DateTimeImmutable('2024-01-15 10:30:00');

        $message->setDate($date);
        $this->assertSame($date, $message->getDate());
    }

    public function testAddressManagement(): void
    {
        $message = new Message(1);

        $from = new MessageAddress('sender@example.com', 'John Doe');
        $message->addFrom($from);

        $this->assertCount(1, $message->getFrom());
        $this->assertSame($from, $message->getFrom()[0]);

        $to = new MessageAddress('recipient@example.com', 'Jane Smith');
        $message->addTo($to);

        $this->assertCount(1, $message->getTo());
    }

    public function testTextBodyManagement(): void
    {
        $message = new Message(1);

        $this->assertNull($message->getTextBody());

        $message->setTextBody('Hello World');
        $this->assertSame('Hello World', $message->getTextBody());

        $message->appendTextBody(' - More text');
        $this->assertSame('Hello World - More text', $message->getTextBody());
    }

    public function testHtmlBodyManagement(): void
    {
        $message = new Message(1);

        $this->assertNull($message->getHtmlBody());

        $message->setHtmlBody('<p>Hello</p>');
        $this->assertSame('<p>Hello</p>', $message->getHtmlBody());

        $message->appendHtmlBody('<p>World</p>');
        $this->assertSame('<p>Hello</p><p>World</p>', $message->getHtmlBody());
    }

    public function testGetPreviewBodyFromTextBody(): void
    {
        $message = new Message(1);
        $message->setTextBody('This is a plain text message');

        $preview = $message->getPreviewBody();
        $this->assertSame('This is a plain text message', $preview);
    }

    public function testGetPreviewBodyFallsBackToHtmlBody(): void
    {
        $message = new Message(1);
        $message->setHtmlBody('<p>HTML <strong>content</strong></p>');

        $preview = $message->getPreviewBody();
        $this->assertSame('HTML content', $preview);
    }

    public function testGetPreviewBodyStripsExcessiveWhitespace(): void
    {
        $message = new Message(1);
        $message->setTextBody("Line 1\n\n\n  Line 2    Line 3");

        $preview = $message->getPreviewBody();
        $this->assertSame('Line 1 Line 2 Line 3', $preview);
    }

    public function testAttachmentManagement(): void
    {
        $message = new Message(1);

        $this->assertCount(0, $message->getAttachments());

        $attachment = new Attachment('test.pdf', 'application/pdf', 'binary-content');
        $message->addAttachment($attachment);

        $this->assertCount(1, $message->getAttachments());
        $this->assertSame($attachment, $message->getAttachments()[0]);
        $this->assertSame(14, $attachment->getSize()); // strlen('binary-content')
    }

    public function testFlagManagement(): void
    {
        $message = new Message(1);

        $this->assertFalse($message->isSeen());
        $this->assertFalse($message->isAnswered());

        $message->setSeen(true);
        $message->setAnswered(true);

        $this->assertTrue($message->isSeen());
        $this->assertTrue($message->isAnswered());
    }

    public function testSizeManagement(): void
    {
        $message = new Message(1);

        $this->assertSame(0, $message->getSize());

        $message->setSize(12345);
        $this->assertSame(12345, $message->getSize());
    }

    public function testHeadersManagement(): void
    {
        $message = new Message(1);

        $this->assertCount(0, $message->getHeaders());

        $headers = [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Custom' => 'custom-value',
        ];

        $message->setHeaders($headers);
        $this->assertSame($headers, $message->getHeaders());
    }
}
