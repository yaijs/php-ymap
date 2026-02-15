<?php
declare(strict_types=1);

namespace Yai\Ymap\Tests;

use PHPUnit\Framework\TestCase;
use Yai\Ymap\Connection\ConnectionLibrary\Address;
use stdClass;

class AddressTest extends TestCase
{
    public function testToString(): void
    {
        $addrObj = new stdClass();
        $addrObj->personal = "John Doe";
        $addrObj->mailbox = "john";
        $addrObj->host = "example.com";

        $address = new Address($addrObj);

        $this->assertEquals("John Doe <john@example.com>", (string)$address);
    }
}
