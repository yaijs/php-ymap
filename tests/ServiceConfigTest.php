<?php declare(strict_types=1);

namespace Yai\Ymap\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Yai\Ymap\ServiceConfig;

final class ServiceConfigTest extends TestCase
{
    public function testInvalidFieldThrowsException(): void
    {
        $config = new ServiceConfig();

        $this->expectException(InvalidArgumentException::class);
        $config->setFields(['uid', 'unknown-field']);
    }

    public function testBuildFetchOptionsRespectsRequestedFields(): void
    {
        $config = new ServiceConfig();
        $options = $config->buildFetchOptions(['uid', 'subject']);

        $this->assertFalse($options->shouldFetchAttachments());
        $this->assertFalse($options->shouldFetchAttachmentContent());
        $this->assertFalse($options->shouldFetchTextBody());
        $this->assertFalse($options->shouldFetchHtmlBody());

        $config->includeAttachmentContent = true;
        $optionsWithAttachments = $config->buildFetchOptions(['uid', 'attachments']);

        $this->assertTrue($optionsWithAttachments->shouldFetchAttachments());
        $this->assertTrue($optionsWithAttachments->shouldFetchAttachmentContent());
    }

    public function testGetActiveFieldsAlwaysContainsUid(): void
    {
        $config = new ServiceConfig();
        $config->setFields(['subject']);
        $config->setExcludeFields(['subject']);

        $fields = $config->getActiveFields();
        $this->assertSame(['uid'], $fields);
    }
}
