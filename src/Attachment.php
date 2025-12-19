<?php declare(strict_types=1);

namespace Yai\Ymap;

use function strlen;

final class Attachment
{
    private string $filename;

    private string $mimeType;

    private ?string $content;

    private bool $inline;

    private ?string $contentId;

    /** @var callable|null */
    private $contentLoader;

    private ?int $size;

    private ?string $partNumber;

    /**
     * @param callable():string|null $contentLoader
     */
    public function __construct(
        string $filename,
        string $mimeType,
        ?string $content,
        bool $inline = false,
        ?string $contentId = null,
        ?int $size = null,
        ?string $partNumber = null,
        ?callable $contentLoader = null
    ) {
        $this->filename = $filename;
        $this->mimeType = $mimeType;
        $this->content = $content;
        $this->inline = $inline;
        $this->contentId = $contentId;
        $this->size = $size;
        $this->partNumber = $partNumber;
        $this->contentLoader = $contentLoader;
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
        if (null === $this->content && null !== $this->contentLoader) {
            $loaded = ($this->contentLoader)();
            $this->content = $loaded;
            $this->size = strlen($loaded);
            $this->contentLoader = null;
        }

        return $this->content ?? '';
    }

    public function hasContent(): bool
    {
        return null !== $this->content;
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
        if (null !== $this->size) {
            return $this->size;
        }

        return strlen($this->content ?? '');
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    /**
     * @param callable():string|null $loader
     */
    public function setContentLoader(?callable $loader): void
    {
        $this->contentLoader = $loader;
    }

    public function getPartNumber(): ?string
    {
        return $this->partNumber;
    }
}
