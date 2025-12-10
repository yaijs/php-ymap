<?php declare(strict_types=1);

namespace Yai\Ymap;

use DateTimeImmutable;
use const ENT_HTML5;
use const ENT_QUOTES;
use function html_entity_decode;
use function preg_replace;
use function strip_tags;
use function trim;

final class Message
{
    private int $uid;

    private ?string $subject = null;

    private ?DateTimeImmutable $date = null;

    /**
     * @var MessageAddress[]
     */
    private array $from = [];

    /**
     * @var MessageAddress[]
     */
    private array $to = [];

    /**
     * @var MessageAddress[]
     */
    private array $cc = [];

    /**
     * @var MessageAddress[]
     */
    private array $bcc = [];

    /**
     * @var MessageAddress[]
     */
    private array $replyTo = [];

    private ?string $textBody = null;

    private ?string $htmlBody = null;

    /**
     * @var Attachment[]
     */
    private array $attachments = [];

    /**
     * @var array<string, string>
     */
    private array $headers = [];

    private bool $seen = false;
    private bool $answered = false;
    private int $size = 0;

    public function __construct(int $uid)
    {
        $this->uid = $uid;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function setSubject(?string $subject): void
    {
        $this->subject = $subject;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setDate(?DateTimeImmutable $date): void
    {
        $this->date = $date;
    }

    public function getDate(): ?DateTimeImmutable
    {
        return $this->date;
    }

    public function addFrom(MessageAddress $address): void
    {
        $this->from[] = $address;
    }

    /**
     * @return MessageAddress[]
     */
    public function getFrom(): array
    {
        return $this->from;
    }

    public function addTo(MessageAddress $address): void
    {
        $this->to[] = $address;
    }

    /**
     * @return MessageAddress[]
     */
    public function getTo(): array
    {
        return $this->to;
    }

    public function addCc(MessageAddress $address): void
    {
        $this->cc[] = $address;
    }

    /**
     * @return MessageAddress[]
     */
    public function getCc(): array
    {
        return $this->cc;
    }

    public function addBcc(MessageAddress $address): void
    {
        $this->bcc[] = $address;
    }

    /**
     * @return MessageAddress[]
     */
    public function getBcc(): array
    {
        return $this->bcc;
    }

    public function addReplyTo(MessageAddress $address): void
    {
        $this->replyTo[] = $address;
    }

    /**
     * @return MessageAddress[]
     */
    public function getReplyTo(): array
    {
        return $this->replyTo;
    }

    public function setTextBody(?string $textBody): void
    {
        $this->textBody = $textBody;
    }

    public function appendTextBody(string $textBody): void
    {
        $this->textBody = ($this->textBody ?? '') . $textBody;
    }

    public function getTextBody(): ?string
    {
        return $this->textBody;
    }

    public function setHtmlBody(?string $htmlBody): void
    {
        $this->htmlBody = $htmlBody;
    }

    public function appendHtmlBody(string $htmlBody): void
    {
        $this->htmlBody = ($this->htmlBody ?? '') . $htmlBody;
    }

    public function getHtmlBody(): ?string
    {
        return $this->htmlBody;
    }

    /**
     * Get a plain text preview of the message content.
     * Falls back to stripped HTML if text body is empty.
     */
    public function getPreviewBody(): string
    {
        $text = trim($this->textBody ?? '');

        if ('' === $text && null !== $this->htmlBody) {
            $text = strip_tags($this->htmlBody);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = trim($text);
        }

        // Collapse excessive whitespace
        return (string) preg_replace('/\s+/', ' ', $text);
    }

    public function addAttachment(Attachment $attachment): void
    {
        $this->attachments[] = $attachment;
    }

    /**
     * @return Attachment[]
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * @param array<string, string> $headers
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setSeen(bool $seen): void
    {
        $this->seen = $seen;
    }

    public function isSeen(): bool
    {
        return $this->seen;
    }

    public function setAnswered(bool $answered): void
    {
        $this->answered = $answered;
    }

    public function isAnswered(): bool
    {
        return $this->answered;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    public function getSize(): int
    {
        return $this->size;
    }

}
