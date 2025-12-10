<?php declare(strict_types=1);

namespace Yai\Ymap;

use function sprintf;

final class MessageAddress
{
    private string $address;

    private ?string $name;

    public function __construct(string $address, ?string $name = null)
    {
        $this->address = $address;
        $this->name = $name;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function __toString(): string
    {
        return $this->name ? sprintf('%s <%s>', $this->name, $this->address) : $this->address;
    }
}
