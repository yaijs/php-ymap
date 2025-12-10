<?php declare(strict_types=1);

namespace Yai\Ymap\Tests;

use PHPUnit\Framework\TestCase;
use Yai\Ymap\Attachment;

final class AttachmentTest extends TestCase
{
    public function testAttachmentCreation(): void
    {
        $attachment = new Attachment(
            'document.pdf',
            'application/pdf',
            'PDF content here'
        );

        $this->assertSame('document.pdf', $attachment->getFilename());
        $this->assertSame('application/pdf', $attachment->getMimeType());
        $this->assertSame('PDF content here', $attachment->getContent());
        $this->assertFalse($attachment->isInline());
        $this->assertNull($attachment->getContentId());
    }

    public function testInlineAttachment(): void
    {
        $attachment = new Attachment(
            'logo.png',
            'image/png',
            'binary-image-data',
            true,
            'logo@example.com'
        );

        $this->assertTrue($attachment->isInline());
        $this->assertSame('logo@example.com', $attachment->getContentId());
    }

    public function testGetSizeCalculatesFromContent(): void
    {
        $content = 'This is test content';
        $attachment = new Attachment('test.txt', 'text/plain', $content);

        $this->assertSame(strlen($content), $attachment->getSize());
        $this->assertSame(20, $attachment->getSize());
    }

    public function testEmptyAttachment(): void
    {
        $attachment = new Attachment('empty.txt', 'text/plain', '');

        $this->assertSame(0, $attachment->getSize());
        $this->assertSame('', $attachment->getContent());
    }

    public function testLargeAttachment(): void
    {
        // Simulate a 1KB attachment
        $content = str_repeat('x', 1024);
        $attachment = new Attachment('large.bin', 'application/octet-stream', $content);

        $this->assertSame(1024, $attachment->getSize());
    }

    public function testDifferentMimeTypes(): void
    {
        $mimeTypes = [
            'image/jpeg' => 'photo.jpg',
            'application/pdf' => 'document.pdf',
            'text/html' => 'page.html',
            'application/zip' => 'archive.zip',
        ];

        foreach ($mimeTypes as $mimeType => $filename) {
            $attachment = new Attachment($filename, $mimeType, 'content');

            $this->assertSame($mimeType, $attachment->getMimeType());
            $this->assertSame($filename, $attachment->getFilename());
        }
    }
}
