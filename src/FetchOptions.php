<?php declare(strict_types=1);

namespace Yai\Ymap;

final class FetchOptions
{
    public function __construct(
        private bool $textBody = true,
        private bool $htmlBody = true,
        private bool $attachments = true,
        private bool $attachmentContent = true
    ) {
    }

    public static function everything(): self
    {
        return new self(true, true, true, true);
    }

    public function withAttachments(bool $attachments): self
    {
        $clone = clone $this;
        $clone->attachments = $attachments;

        return $clone;
    }

    public function withAttachmentContent(bool $attachmentContent): self
    {
        $clone = clone $this;
        $clone->attachmentContent = $attachmentContent;

        return $clone;
    }

    public function withTextBody(bool $textBody): self
    {
        $clone = clone $this;
        $clone->textBody = $textBody;

        return $clone;
    }

    public function withHtmlBody(bool $htmlBody): self
    {
        $clone = clone $this;
        $clone->htmlBody = $htmlBody;

        return $clone;
    }

    public function shouldFetchTextBody(): bool
    {
        return $this->textBody;
    }

    public function shouldFetchHtmlBody(): bool
    {
        return $this->htmlBody;
    }

    public function shouldFetchAttachments(): bool
    {
        return $this->attachments;
    }

    public function shouldFetchAttachmentContent(): bool
    {
        return $this->attachmentContent;
    }
}
