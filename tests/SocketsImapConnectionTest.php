<?php declare(strict_types=1);

namespace Yai\Ymap\Tests;

use PHPUnit\Framework\TestCase;
use Yai\Ymap\Connection\ConnectionLibrary\IMAP;
use Yai\Ymap\Connection\SocketsImapConnection;
use Yai\Ymap\Connection\ConnectionLibrary\ImapProtocol;
use Yai\Ymap\Connection\ConnectionLibrary\Response;
use stdClass;

class SocketsImapConnectionTest extends TestCase
{
    public function testSearch(): void
    {
        $protocol = $this->createMock(ImapProtocol::class);
        $response = $this->createMock(Response::class);

        $protocol->expects($this->once())
            ->method('search')
            ->with(['UNSEEN'], IMAP::ST_UID)
            ->willReturn($response);

        $response->expects($this->once())
            ->method('validatedData')
            ->willReturn(['1', '2', '3']);

        $connection = new SocketsImapConnection();
        $result = $connection->search($protocol, 'UNSEEN', IMAP::SE_UID);

        $this->assertSame([1, 2, 3], $result);
    }

    public function testSearchWithQuotedCriteriaPreservesTokenGrouping(): void
    {
        $protocol = $this->createMock(ImapProtocol::class);
        $response = $this->createMock(Response::class);

        $protocol->expects($this->once())
            ->method('search')
            ->with(['SUBJECT', '"foo bar"', 'FROM', '"john@example.com"'], IMAP::ST_UID)
            ->willReturn($response);

        $response->expects($this->once())
            ->method('validatedData')
            ->willReturn(['11']);

        $connection = new SocketsImapConnection();
        $result = $connection->search(
            $protocol,
            'SUBJECT "foo bar" FROM "john@example.com"',
            IMAP::SE_UID
        );

        $this->assertSame([11], $result);
    }

    public function testFetchOverview(): void
    {
        $protocol = $this->createMock(ImapProtocol::class);
        $response = $this->createMock(Response::class);

        $overviewData = [
            123 => [
                'uid' => 123,
                'subject' => 'Test Subject',
                'from' => 'sender@example.com',
                'flags' => ['\\Seen']
            ]
        ];

        $protocol->expects($this->once())
            ->method('overview')
            ->with('1:10', IMAP::ST_UID)
            ->willReturn($response);

        $response->expects($this->once())
            ->method('validatedData')
            ->willReturn($overviewData);

        $connection = new SocketsImapConnection();
        // FT_UID = 1
        $result = $connection->fetchOverview($protocol, '1:10', 1);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(stdClass::class, $result[0]);
        $this->assertEquals(123, $result[0]->uid);
        $this->assertEquals('Test Subject', $result[0]->subject);
        $this->assertEquals(1, $result[0]->seen);
        $this->assertEquals(0, $result[0]->answered);
    }

    public function testNumMsg(): void
    {
        $protocol = $this->createMock(ImapProtocol::class);
        $response = $this->createMock(Response::class);

        $protocol->expects($this->once())
            ->method('search')
            ->with(['ALL'])
            ->willReturn($response);

        $response->expects($this->once())
             ->method('validatedData')
             ->willReturn([1, 2, 3, 4, 5]);

        $connection = new SocketsImapConnection();
        $count = $connection->numMsg($protocol);

        $this->assertEquals(5, $count);
    }
    public function testFetchStructure(): void
    {
        $protocol = $this->createMock(ImapProtocol::class);
        $response = $this->createMock(Response::class);

        // Mock structure response: [UID => ['BODYSTRUCTURE' => [...]]]
        // Structure: type, subtype, params, id, desc, encoding, size, lines, ...
        // TEXT PLAIN ("charset" "utf-8") NIL NIL "7BIT" 100 5
        $structureData = [
            'TEXT', 'PLAIN', ['charset', 'utf-8'], null, null, '7BIT', 100, 5
        ];

        $responseData = [
            123 => $structureData
        ];

        $protocol->expects($this->once())
            ->method('fetch')
            ->with('BODYSTRUCTURE', 123, null, IMAP::ST_UID)
            ->willReturn($response);

        $response->expects($this->once())
            ->method('validatedData')
            ->willReturn($responseData);

        $connection = new SocketsImapConnection();
        // FT_UID = 1
        $result = $connection->fetchStructure($protocol, 123, 1);

        $this->assertNotFalse($result);
        $this->assertEquals(0, $result->type); // TEXT = 0
        $this->assertEquals('PLAIN', $result->subtype);
        $this->assertEquals(100, $result->size);
        $this->assertEquals(5, $result->lines);
        $this->assertEquals(1, $result->ifparameters);
        $this->assertEquals('charset', $result->parameters[0]->attribute);
        $this->assertEquals('utf-8', $result->parameters[0]->value);
    }

    public function testFetchStructureUnwrapsNestedSingleArray(): void
    {
        $protocol = $this->createMock(ImapProtocol::class);
        $response = $this->createMock(Response::class);

        $plainPart = ['TEXT', 'PLAIN', ['charset', 'utf-8'], null, null, '7BIT', 120, 7];
        $htmlPart = ['TEXT', 'HTML', ['charset', 'utf-8'], null, null, 'QUOTED-PRINTABLE', 240, 20];
        $multipart = [$plainPart, $htmlPart, 'ALTERNATIVE'];

        // Extra wrapper shape that previously broke mapping into empty multipart parts.
        $responseData = [
            123 => [$multipart],
        ];

        $protocol->expects($this->once())
            ->method('fetch')
            ->with('BODYSTRUCTURE', 123, null, IMAP::ST_UID)
            ->willReturn($response);

        $response->expects($this->once())
            ->method('validatedData')
            ->willReturn($responseData);

        $connection = new SocketsImapConnection();
        $result = $connection->fetchStructure($protocol, 123, IMAP::FT_UID);

        $this->assertNotFalse($result);
        $this->assertEquals(1, $result->type); // MULTIPART
        $this->assertCount(2, $result->parts);
        $this->assertEquals('ALTERNATIVE', $result->subtype);
        $this->assertEquals(0, $result->parts[0]->type); // TEXT
        $this->assertEquals('PLAIN', $result->parts[0]->subtype);
        $this->assertEquals(0, $result->parts[1]->type); // TEXT
        $this->assertEquals('HTML', $result->parts[1]->subtype);
    }
    public function testFetchHeader(): void
    {
        $protocol = $this->createMock(ImapProtocol::class);
        $response = $this->createMock(Response::class);

        $headerString = "Subject: Test\r\nFrom: me@example.com\r\n\r\n";

        $protocol->expects($this->once())
            ->method('headers')
            ->with(123, "RFC822", IMAP::ST_UID)
            ->willReturn($response);

        $response->expects($this->once())
            ->method('validatedData')
            ->willReturn([123 => $headerString]);

        $connection = new SocketsImapConnection();
        // FT_UID = 1
        $result = $connection->fetchHeader($protocol, 123, 1);

        $this->assertEquals($headerString, $result);
    }
}
