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
    public function testBuildsToolsetsFromExplicitToolGroups(): void
    {
        $registry = $this->buildRegistry([
            McpToolsetRegistry::LIST_TOOLSETS_TOOL,
            McpToolsetRegistry::ENABLE_TOOLSET_TOOL,
            'shopware-entity-search',
            'shopware-entity-read',
            'shopware-order-state',
            'ungrouped-tool',
        ]);

        $toolsetRegistry = new McpToolsetRegistry(new McpCapabilityCatalog(
            $registry,
            $this->stubPrivilegeProvider(),
            toolGroups: [
                McpToolsetRegistry::LIST_TOOLSETS_TOOL => 'default',
                McpToolsetRegistry::ENABLE_TOOLSET_TOOL => 'default',
                'shopware-entity-search' => 'entity',
                'shopware-entity-read' => 'entity',
                'shopware-order-state' => 'order',
            ],
        ));

        $toolsets = $toolsetRegistry->toolsets();
        $toolsetsByName = array_column($toolsets, null, 'name');

        // The ungrouped tool falls to the "other" catch-all, which is surfaced as an enable-able
        // toolset so it still has a guaranteed enable path. Only the "default" group is excluded.
        static::assertSame(['entity', 'order', 'other'], array_keys($toolsetsByName));
        static::assertSame(['shopware-entity-read', 'shopware-entity-search'], $toolsetsByName['entity']['tools']);
        static::assertSame(['shopware-order-state'], $toolsetsByName['order']['tools']);
        static::assertSame(['ungrouped-tool'], $toolsetsByName['other']['tools']);
        static::assertSame('Entity tools', $toolsetsByName['entity']['title']);
        static::assertSame('Other tools', $toolsetsByName['other']['title']);
        static::assertSame('Tools explicitly assigned to the "entity" MCP tool group.', $toolsetsByName['entity']['description']);
    }

    public function testBuildsToolsetPerAppFromRuntimeAppGroups(): void
    {
        $registry = $this->buildRegistry([
            McpToolsetRegistry::ENABLE_TOOLSET_TOOL,
            'shopware-entity-search',
            'my-erp-sync-orders',
            'my-erp-read-stock',
        ]);

        $toolsetRegistry = new McpToolsetRegistry(new McpCapabilityCatalog(
            $registry,
            $this->stubPrivilegeProvider([
                'my-erp-sync-orders' => 'my-erp',
                'my-erp-read-stock' => 'my-erp',
            ]),
            toolGroups: [
                McpToolsetRegistry::ENABLE_TOOLSET_TOOL => 'default',
                'shopware-entity-search' => 'entity',
            ],
        ));

        $toolsetsByName = array_column($toolsetRegistry->toolsets(), null, 'name');

        // App tools without a compile-time #[McpToolGroup] are grouped under their owning app,
        // forming a real toolset instead of vanishing into "other".
        static::assertSame(['entity', 'my-erp'], array_keys($toolsetsByName));
        static::assertSame(['my-erp-read-stock', 'my-erp-sync-orders'], $toolsetsByName['my-erp']['tools']);
        static::assertNotNull($toolsetRegistry->find('my-erp'));
    }

    public function testFindReturnsToolsetByName(): void
    {
        $registry = $this->buildRegistry([
            McpToolsetRegistry::LIST_TOOLSETS_TOOL,
            McpToolsetRegistry::ENABLE_TOOLSET_TOOL,
            'shopware-entity-search',
        ]);

        $toolsetRegistry = new McpToolsetRegistry(new McpCapabilityCatalog(
            $registry,
            $this->stubPrivilegeProvider(),
            toolGroups: [
                McpToolsetRegistry::LIST_TOOLSETS_TOOL => 'default',
                McpToolsetRegistry::ENABLE_TOOLSET_TOOL => 'default',
                'shopware-entity-search' => 'entity',
            ],
        ));

        static::assertSame('entity', $toolsetRegistry->find('entity')['name'] ?? null);
        static::assertNull($toolsetRegistry->find('default'));
        static::assertNull($toolsetRegistry->find('missing'));
    }

    public function testAdvertisedToolsReturnsEnabledToolsetTools(): void
    {
        $registry = $this->buildRegistry([
            McpToolsetRegistry::LIST_TOOLSETS_TOOL,
            McpToolsetRegistry::ENABLE_TOOLSET_TOOL,
            'shopware-entity-search',
            'shopware-entity-read',
            'shopware-order-state',
        ]);

        $toolsetRegistry = new McpToolsetRegistry(new McpCapabilityCatalog(
            $registry,
            $this->stubPrivilegeProvider(),
            toolGroups: [
                McpToolsetRegistry::LIST_TOOLSETS_TOOL => 'default',
                McpToolsetRegistry::ENABLE_TOOLSET_TOOL => 'default',
                'shopware-entity-search' => 'entity',
                'shopware-entity-read' => 'entity',
                'shopware-order-state' => 'order',
            ],
        ));

        static::assertSame([], $toolsetRegistry->advertisedTools([]));

        static::assertSame(
            [
                'shopware-entity-read',
                'shopware-entity-search',
            ],
            $toolsetRegistry->advertisedTools(['entity']),
        );

        static::assertSame(
            [
                'shopware-entity-read',
                'shopware-entity-search',
                'shopware-order-state',
            ],
            $toolsetRegistry->advertisedTools(['entity', 'order']),
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
            );
        }

        return $registry;
    }

    /**
     * @param array<string, string> $appGroups
     */
    private function stubPrivilegeProvider(array $appGroups = []): AppMcpPrivilegeProvider
    {
        $stub = static::createStub(AppMcpPrivilegeProvider::class);
        $stub->method('getAppToolPrivileges')->willReturn([]);
        $stub->method('getAppToolGroups')->willReturn($appGroups);

        return $stub;
    }
}
