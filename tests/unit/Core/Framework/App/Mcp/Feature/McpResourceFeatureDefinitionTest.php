<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Mcp\Feature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Feature\TranslatedString;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Mcp\Feature\McpResourceConfig;
use Shopware\Core\Framework\App\Mcp\Feature\McpResourceFeatureDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpResourceFeatureDefinition::class)]
class McpResourceFeatureDefinitionTest extends TestCase
{
    private McpResourceFeatureDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new McpResourceFeatureDefinition();
    }

    public function testType(): void
    {
        static::assertSame('mcp_resource', $this->definition->getType());
        static::assertSame(McpResourceConfig::class, $this->definition->getConfigClass());
    }

    public function testAnAppWithoutMcpXmlDeclaresNothing(): void
    {
        static::assertSame([], $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__),
            'en-GB',
        ));
    }

    public function testResourcesAreReadFromMcpXml(): void
    {
        $configs = $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'en-GB',
        );

        static::assertEquals([
            new McpResourceConfig(
                'order-stats',
                'app-example://order-stats',
                'https://app.example.com/mcp/resource/order-stats',
                'application/json',
                new TranslatedString(['en-GB' => 'Order Stats']),
                new TranslatedString(['en-GB' => 'Live order statistics']),
            ),
            new McpResourceConfig(
                'product-list',
                'app-example://product-list',
                'https://app.example.com/mcp/resource/product-list',
                null,
                new TranslatedString(['en-GB' => 'Product List']),
                new TranslatedString([]),
            ),
        ], $configs);
    }

    public function testAStoredResourceIsReplacedByTheDeclaredOne(): void
    {
        $declared = new McpResourceConfig(
            'order-stats',
            'app-example://order-stats',
            'https://app.example.com/mcp/resource/order-stats',
            'application/json',
            new TranslatedString(['en-GB' => 'Order Stats']),
            new TranslatedString(['en-GB' => 'Live order statistics']),
        );

        $stored = new McpResourceConfig('order-stats', 'app-example://stale', 'https://stale.example.com', null, new TranslatedString(['en-GB' => 'Old']), new TranslatedString([]));

        $hydrated = $this->definition->fromPayload($this->definition->toPayload($declared, $stored));

        static::assertEquals($declared, $hydrated);
    }
}
