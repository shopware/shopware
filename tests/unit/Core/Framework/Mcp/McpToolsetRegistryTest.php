<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp;

use Mcp\Capability\Registry;
use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\Loader\AppMcpPrivilegeProvider;
use Shopware\Core\Framework\Mcp\McpCapabilityCatalog;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;

/**
 * @internal
 */
#[CoversClass(McpToolsetRegistry::class)]
class McpToolsetRegistryTest extends TestCase
{
    public function testBuildsDefaultCoreAndFallbackToolsetsFromToolNames(): void
    {
        $registry = $this->buildRegistry([
            McpToolsetRegistry::LIST_TOOLSETS_TOOL,
            McpToolsetRegistry::ENABLE_TOOLSET_TOOL,
            'shopware-entity-search',
            'shopware-entity-read',
            'shopware-system-config-read',
            'shopware-order-state',
            'shopware-media-upload',
            'merchant-cart-manage',
            'singleton',
        ]);

        $toolsetRegistry = new McpToolsetRegistry(new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider()));

        $toolsets = $toolsetRegistry->toolsets();
        $toolsetsByName = array_column($toolsets, null, 'name');

        static::assertSame(
            [McpToolsetRegistry::LIST_TOOLSETS_TOOL, McpToolsetRegistry::ENABLE_TOOLSET_TOOL],
            $toolsetsByName[McpToolsetRegistry::DEFAULT_TOOLSET]['tools'],
        );
        static::assertTrue($toolsetsByName[McpToolsetRegistry::DEFAULT_TOOLSET]['enabledByDefault']);
        static::assertSame(['shopware-entity-read', 'shopware-entity-search'], $toolsetsByName['shopware-entity']['tools']);
        static::assertSame(['shopware-system-config-read'], $toolsetsByName['shopware-system-config']['tools']);
        static::assertSame(['shopware-order-state'], $toolsetsByName['shopware-order']['tools']);
        static::assertSame(['shopware-media-upload'], $toolsetsByName['shopware-media']['tools']);
        static::assertSame(['merchant-cart-manage'], $toolsetsByName['merchant-cart']['tools']);
        static::assertSame(['singleton'], $toolsetsByName['singleton']['tools']);
        static::assertSame('Singleton tools', $toolsetsByName['singleton']['title']);
    }

    public function testFindReturnsToolsetByName(): void
    {
        $registry = $this->buildRegistry([
            McpToolsetRegistry::LIST_TOOLSETS_TOOL,
            McpToolsetRegistry::ENABLE_TOOLSET_TOOL,
            'shopware-entity-search',
        ]);

        $toolsetRegistry = new McpToolsetRegistry(new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider()));

        static::assertSame('shopware-entity', $toolsetRegistry->find('shopware-entity')['name'] ?? null);
        static::assertNull($toolsetRegistry->find('missing'));
    }

    public function testAdvertisedToolsReturnsDefaultPlusEnabledToolsets(): void
    {
        $registry = $this->buildRegistry([
            McpToolsetRegistry::LIST_TOOLSETS_TOOL,
            McpToolsetRegistry::ENABLE_TOOLSET_TOOL,
            'shopware-entity-search',
            'shopware-system-config-read',
        ]);

        $toolsetRegistry = new McpToolsetRegistry(new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider()));

        static::assertSame(
            [
                McpToolsetRegistry::ENABLE_TOOLSET_TOOL,
                McpToolsetRegistry::LIST_TOOLSETS_TOOL,
            ],
            $toolsetRegistry->advertisedTools([]),
        );

        static::assertSame(
            [
                'shopware-entity-search',
                McpToolsetRegistry::ENABLE_TOOLSET_TOOL,
                McpToolsetRegistry::LIST_TOOLSETS_TOOL,
            ],
            $toolsetRegistry->advertisedTools(['shopware-entity']),
        );
    }

    /**
     * @param list<string> $toolNames
     */
    private function buildRegistry(array $toolNames): Registry
    {
        $registry = new Registry();

        foreach ($toolNames as $toolName) {
            $registry->registerTool(
                new Tool($toolName, null, ['type' => 'object', 'properties' => [], 'required' => []], null, null),
                'Acme\\' . str_replace('-', '', ucwords($toolName, '-')),
                true,
            );
        }

        return $registry;
    }

    private function stubPrivilegeProvider(): AppMcpPrivilegeProvider
    {
        $stub = static::createStub(AppMcpPrivilegeProvider::class);
        $stub->method('getAppToolPrivileges')->willReturn([]);

        return $stub;
    }
}
