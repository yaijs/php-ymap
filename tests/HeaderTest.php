<?php
declare(strict_types=1);

namespace Yai\Ymap\Tests;

use PHPUnit\Framework\TestCase;
use Yai\Ymap\Connection\ConnectionLibrary\Header;
use Yai\Ymap\Connection\ConnectionLibrary\Config;

class HeaderTest extends TestCase
{
    public function testConstructorWithEmptyConfig(): void
    {
        $config = new Config([]);
        $headerRaw = "Subject: Test Header\r\n\r\n";

        $header = new Header($headerRaw, $config);

        $this->assertInstanceOf(Header::class, $header);
        $this->assertEquals("Test Header", $header->subject);
    }
    public function testParseDateToStringConversion(): void
    {
        $config = new Config([]);
        // Including a Date header will trigger parseDate, which sets 'date' as DateTimeImmutable
        // Then extractHeaderExtensions will try to cast it to string
        $headerRaw = "Date: Wed, 14 Feb 2026 10:00:00 +0000\r\nSubject: Test\r\n\r\n";

        $header = new Header($headerRaw, $config);

        $this->assertInstanceOf(Header::class, $header);
        // Header properties return Attribute objects
        $this->assertInstanceOf(\DateTimeImmutable::class, $header->date->first());
    }
}
