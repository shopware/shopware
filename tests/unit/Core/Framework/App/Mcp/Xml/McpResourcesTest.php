<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Mcp\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\App\Mcp\Xml\McpResource;
use Shopware\Core\Framework\App\Mcp\Xml\McpResources;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(McpResources::class)]
#[Package('framework')]
class McpResourcesTest extends TestCase
{
    public function testFromXmlParsesMultipleResources(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/../_fixtures/mcp.xml');

        $resources = $mcp->getResources();
        static::assertNotNull($resources);
        static::assertCount(2, $resources->getResources());
        static::assertContainsOnlyInstancesOf(McpResource::class, $resources->getResources());
    }

    public function testGetResourcesReturnsResourcesInOrder(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/../_fixtures/mcp.xml');

        $resources = $mcp->getResources();
        static::assertNotNull($resources);

        $names = array_map(
            static fn (McpResource $r) => $r->getName(),
            $resources->getResources(),
        );

        static::assertSame(['order-stats', 'product-list'], $names);
    }

    public function testFromArrayCreatesResources(): void
    {
        $resource = McpResource::fromArray([
            'name' => 'test-resource',
            'uri' => 'app://test',
            'url' => 'https://example.com/resource',
            'mimeType' => 'application/json',
            'label' => ['en-GB' => 'Test'],
            'description' => [],
        ]);

        $resources = McpResources::fromArray(['resources' => [$resource]]);

        static::assertCount(1, $resources->getResources());
        static::assertSame('test-resource', $resources->getResources()[0]->getName());
    }
}
