<?php declare(strict_types=1);

namespace Yai\Ymap;

use function strlen;

final class Attachment
{
    private string $filename;

    private string $mimeType;

    private string $content;

    private bool $inline;

    private ?string $contentId;

    public function __construct(
        string $filename,
        string $mimeType,
        string $content,
        bool $inline = false,
        ?string $contentId = null
    ) {
        $this->filename = $filename;
        $this->mimeType = $mimeType;
        $this->content = $content;
        $this->inline = $inline;
        $this->contentId = $contentId;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function isInline(): bool
    {
        return $this->inline;
    }

    public function getContentId(): ?string
    {
        return $this->contentId;
    }

    public function getSize(): int
    {
        return strlen($this->content);
    }
}
