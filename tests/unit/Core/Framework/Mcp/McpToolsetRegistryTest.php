<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp;

use Mcp\Capability\Registry;
use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopware\Core\Framework\Mcp\Loader\AppMcpPrivilegeProvider;
use Shopware\Core\Framework\Mcp\McpCapabilityCatalog;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolsetRegistry::class)]
class McpToolsetRegistryTest extends TestCase
{
    public function testBuildsToolsetsFromExplicitToolGroups(): void
    {
        $registry = $this->buildRegistry([
            McpToolsetRegistry::LIST_TOOLSETS_TOOL => 'List Toolsets',
            McpToolsetRegistry::ENABLE_TOOLSET_TOOL => 'Enable Toolset',
            'shopware-entity-search' => 'Entity Search',
            'shopware-entity-read' => 'Entity Read',
            'shopware-order-state' => 'Order State',
            'ungrouped-tool' => 'Ungrouped Tool',
        ]);

        $toolsetRegistry = new McpToolsetRegistry(new McpCapabilityCatalog(
            $registry,
            $this->stubPrivilegeProvider(),
            toolGroups: [
                McpToolsetRegistry::LIST_TOOLSETS_TOOL => 'discovery',
                McpToolsetRegistry::ENABLE_TOOLSET_TOOL => 'discovery',
                'shopware-entity-search' => 'entity',
                'shopware-entity-read' => 'entity',
                'shopware-order-state' => 'order',
            ],
        ));

        $toolsets = $toolsetRegistry->toolsets();
        $toolsetsByName = array_column($toolsets, null, 'name');

        // A tool without an explicit group uses its first name segment as an enable-able toolset.
        static::assertSame(['entity', 'order', 'ungrouped'], array_keys($toolsetsByName));
        static::assertSame(
            [
                ['name' => 'shopware-entity-read', 'title' => 'Entity Read'],
                ['name' => 'shopware-entity-search', 'title' => 'Entity Search'],
            ],
            $toolsetsByName['entity']['tools'],
        );
        static::assertSame([['name' => 'shopware-order-state', 'title' => 'Order State']], $toolsetsByName['order']['tools']);
        static::assertSame([['name' => 'ungrouped-tool', 'title' => 'Ungrouped Tool']], $toolsetsByName['ungrouped']['tools']);
        static::assertSame('Entity tools', $toolsetsByName['entity']['title']);
        static::assertSame('Ungrouped tools', $toolsetsByName['ungrouped']['title']);
        // Each tool's own title is the semantic payload of a toolset. The former synthesized group
        // description ("Tools explicitly assigned to ...") only restated the slug, so none is emitted;
        // StoreApiMcpCapabilityDiscoveryTest asserts its absence on the wire.
    }

    public function testBuildsToolsetPerAppFromRuntimeAppGroups(): void
    {
        $registry = $this->buildRegistry([
            McpToolsetRegistry::ENABLE_TOOLSET_TOOL => 'Enable Toolset',
            'shopware-entity-search' => 'Entity Search',
            'my-erp-sync-orders' => null,
            'my-erp-read-stock' => null,
        ]);

        $toolsetRegistry = new McpToolsetRegistry(new McpCapabilityCatalog(
            $registry,
            $this->stubPrivilegeProvider([
                'my-erp-sync-orders' => 'my-erp',
                'my-erp-read-stock' => 'my-erp',
            ]),
            toolGroups: [
                McpToolsetRegistry::ENABLE_TOOLSET_TOOL => 'discovery',
                'shopware-entity-search' => 'entity',
            ],
        ));

        $toolsetsByName = array_column($toolsetRegistry->toolsets(), null, 'name');

        // App tools without a compile-time #[McpToolGroup] are grouped under their owning app,
        // forming a real toolset instead of vanishing into "other".
        static::assertSame(['entity', 'my-erp'], array_keys($toolsetsByName));
        // App tools registered without a title keep a null title rather than a synthesized one.
        static::assertSame(
            [
                ['name' => 'my-erp-read-stock', 'title' => null],
                ['name' => 'my-erp-sync-orders', 'title' => null],
            ],
            $toolsetsByName['my-erp']['tools'],
        );
        static::assertNotNull($toolsetRegistry->find('my-erp'));
    }

    public function testFindReturnsToolsetByName(): void
    {
        $registry = $this->buildRegistry([
            McpToolsetRegistry::LIST_TOOLSETS_TOOL => 'List Toolsets',
            McpToolsetRegistry::ENABLE_TOOLSET_TOOL => 'Enable Toolset',
            'shopware-entity-search' => 'Entity Search',
        ]);

        $toolsetRegistry = new McpToolsetRegistry(new McpCapabilityCatalog(
            $registry,
            $this->stubPrivilegeProvider(),
            toolGroups: [
                McpToolsetRegistry::LIST_TOOLSETS_TOOL => 'discovery',
                McpToolsetRegistry::ENABLE_TOOLSET_TOOL => 'discovery',
                'shopware-entity-search' => 'entity',
            ],
        ));

        static::assertSame('entity', $toolsetRegistry->find('entity')['name'] ?? null);
        static::assertNull($toolsetRegistry->find('discovery'));
        static::assertNull($toolsetRegistry->find('missing'));
    }

    public function testAdvertisedToolsReturnsEnabledToolsetTools(): void
    {
        $registry = $this->buildRegistry([
            McpToolsetRegistry::LIST_TOOLSETS_TOOL => 'List Toolsets',
            McpToolsetRegistry::ENABLE_TOOLSET_TOOL => 'Enable Toolset',
            'shopware-entity-search' => 'Entity Search',
            'shopware-entity-read' => 'Entity Read',
            'shopware-order-state' => 'Order State',
        ]);

        $toolsetRegistry = new McpToolsetRegistry(new McpCapabilityCatalog(
            $registry,
            $this->stubPrivilegeProvider(),
            toolGroups: [
                McpToolsetRegistry::LIST_TOOLSETS_TOOL => 'discovery',
                McpToolsetRegistry::ENABLE_TOOLSET_TOOL => 'discovery',
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

    public function testToolsetsAreScopedToTheCurrentAllowlist(): void
    {
        $registry = $this->buildRegistry([
            McpToolsetRegistry::ENABLE_TOOLSET_TOOL => 'Enable Toolset',
            'shopware-entity-search' => 'Entity Search',
            'shopware-entity-read' => 'Entity Read',
            'shopware-order-state' => 'Order State',
        ]);

        $toolsetRegistry = new McpToolsetRegistry(
            new McpCapabilityCatalog(
                $registry,
                $this->stubPrivilegeProvider(),
                toolGroups: [
                    McpToolsetRegistry::ENABLE_TOOLSET_TOOL => 'discovery',
                    'shopware-entity-search' => 'entity',
                    'shopware-entity-read' => 'entity',
                    'shopware-order-state' => 'order',
                ],
            ),
            $this->stubAllowlistProvider(['shopware-entity-search']),
        );

        $toolsetsByName = array_column($toolsetRegistry->toolsets(), null, 'name');

        // Discovery stays inside the allowlist: only the allowed tool surfaces. The denied
        // "entity-read" and the entirely-denied "order" toolset never leak through list/enable.
        static::assertSame(['entity'], array_keys($toolsetsByName));
        static::assertSame([['name' => 'shopware-entity-search', 'title' => 'Entity Search']], $toolsetsByName['entity']['tools']);
        static::assertNull($toolsetRegistry->find('order'));
        static::assertSame([], $toolsetRegistry->advertisedTools(['order']));
    }

    /**
     * @param array<string, ?string> $toolTitles tool-name => title (null mirrors a runtime app tool
     *                                           registered without a title)
     */
    private function buildRegistry(array $toolTitles): Registry
    {
        $registry = new Registry();

        foreach ($toolTitles as $toolName => $title) {
            $registry->registerTool(
                new Tool($toolName, $title, ['type' => 'object', 'properties' => [], 'required' => []], null, null),
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

    /**
     * @param list<string>|null $tools
     */
    private function stubAllowlistProvider(?array $tools): McpAllowlistProvider
    {
        $stub = static::createStub(McpAllowlistProvider::class);
        $stub->method('toolsForCurrentRequest')->willReturn($tools);

        return $stub;
    }
}
