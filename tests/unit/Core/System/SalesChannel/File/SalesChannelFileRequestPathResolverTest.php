<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\File\SalesChannelFileRequestPathResolver;
use Shopware\Core\System\SalesChannel\SalesChannelException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelFileRequestPathResolver::class)]
class SalesChannelFileRequestPathResolverTest extends TestCase
{
    public function testItBuildsTemplatePathForNestedPublicFile(): void
    {
        $templatePath = (new SalesChannelFileRequestPathResolver())->buildTemplatePath('agentic', '.well-known/ucp.json');

        static::assertSame('files/agentic/.well-known/ucp.json.twig', $templatePath);
    }

    public function testItRejectsFileFamilyWithTrailingNewline(): void
    {
        $this->expectExceptionObject(SalesChannelException::invalidSalesChannelFileFamily("agentic\n"));

        (new SalesChannelFileRequestPathResolver())->validateFileFamily("agentic\n");
    }

    public function testItRejectsParentSegmentWithTrailingNewline(): void
    {
        $this->expectExceptionObject(SalesChannelException::invalidSalesChannelFilePath("..\n/ucp.json"));

        (new SalesChannelFileRequestPathResolver())->buildTemplatePath('agentic', "..\n/ucp.json");
    }

    public function testItRejectsFileFamilyLongerThanDatabaseColumn(): void
    {
        $fileFamily = str_repeat('a', 65);

        $this->expectExceptionObject(SalesChannelException::invalidSalesChannelFileFamily($fileFamily));

        (new SalesChannelFileRequestPathResolver())->validateFileFamily($fileFamily);
    }
}
