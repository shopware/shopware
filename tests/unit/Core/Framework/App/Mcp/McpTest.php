<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\App\Mcp\Xml\McpPrompts;
use Shopware\Core\Framework\App\Mcp\Xml\McpResources;
use Shopware\Core\Framework\App\Mcp\Xml\McpTools;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(Mcp::class)]
#[Package('framework')]
class McpTest extends TestCase
{
    public function testCreateFromXmlFileWithTools(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/_fixtures/mcp.xml');

        static::assertSame(__DIR__ . '/_fixtures', $mcp->getPath());
        static::assertNotNull($mcp->getTools());
        static::assertCount(2, $mcp->getTools()->getTools());
    }

    public function testCreateFromXmlFileWithPrompts(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/_fixtures/mcp.xml');

        static::assertInstanceOf(McpPrompts::class, $mcp->getPrompts());
        static::assertCount(2, $mcp->getPrompts()->getPrompts());
    }

    public function testCreateFromXmlFileWithResources(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/_fixtures/mcp.xml');

        static::assertInstanceOf(McpResources::class, $mcp->getResources());
        static::assertCount(2, $mcp->getResources()->getResources());
    }

    public function testCreateFromXmlFileWithoutTools(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/_fixtures/mcp_empty.xml');

        static::assertSame(__DIR__ . '/_fixtures', $mcp->getPath());
        static::assertNull($mcp->getTools());
    }

    public function testCreateFromXmlFileWithoutPromptsOrResources(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/_fixtures/mcp_empty.xml');

        static::assertNull($mcp->getPrompts());
        static::assertNull($mcp->getResources());
    }

    public function testSetPath(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/_fixtures/mcp.xml');

        $mcp->setPath('/new/path');

        static::assertSame('/new/path', $mcp->getPath());
    }

    public function testFileNotReadableThrowsException(): void
    {
        $this->expectException(AppException::class);

        Mcp::createFromXmlFile('/non/existent/path/mcp.xml');
    }

    public function testGetToolsReturnsCorrectToolNames(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/_fixtures/mcp.xml');

        $tools = $mcp->getTools();
        static::assertInstanceOf(McpTools::class, $tools);

        $toolList = $tools->getTools();
        static::assertSame('sync-orders', $toolList[0]->getName());
        static::assertSame('stock-check', $toolList[1]->getName());
    }
}
