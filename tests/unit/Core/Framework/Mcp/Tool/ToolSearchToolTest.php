<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use Mcp\Capability\Registry;
use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlist;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopware\Core\Framework\Mcp\Tool\AbstractToolSearchTool;
use Shopware\Core\Framework\Mcp\Tool\Search\ToolSearch;
use Shopware\Core\Framework\Mcp\Tool\ToolSearchTool;

/**
 * @internal
 */
#[CoversClass(ToolSearchTool::class)]
#[CoversClass(AbstractToolSearchTool::class)]
class ToolSearchToolTest extends TestCase
{
    public function testSearchReturnsMatchingToolDefinitions(): void
    {
        $tool = new ToolSearchTool($this->registry(), new ToolSearch());

        $data = json_decode($tool('read entity'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame('shopware-entity-read', $data['data'][0]['tool']['name']);
        static::assertSame(2, $data['_meta']['totalCandidates']);
    }

    public function testSearchIsScopedToAllowlist(): void
    {
        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forCurrentRequest')->willReturn(new McpAllowlist(
            tools: ['shopware-entity-search'],
            resources: null,
            prompts: null,
        ));

        $tool = new ToolSearchTool($this->registry(), new ToolSearch(), $allowlistProvider);

        $data = json_decode($tool('entity'), true, 512, \JSON_THROW_ON_ERROR);
        $names = array_column(array_column($data['data'], 'tool'), 'name');

        static::assertSame(['shopware-entity-search'], $names);
        static::assertSame(1, $data['_meta']['totalCandidates']);
    }

    public function testSearchToolDoesNotReturnItself(): void
    {
        $registry = $this->registry();
        $registry->registerTool(self::tool('shopware-tool-search', 'Search tools'), 'Acme\\ToolSearch', true);

        $tool = new ToolSearchTool($registry, new ToolSearch());

        $data = json_decode($tool('tool search'), true, 512, \JSON_THROW_ON_ERROR);
        $names = array_column(array_column($data['data'], 'tool'), 'name');

        static::assertNotContains('shopware-tool-search', $names);
    }

    private function registry(): Registry
    {
        $registry = new Registry();
        $registry->registerTool(self::tool('shopware-entity-search', 'Search entities'), 'Acme\\SearchTool', true);
        $registry->registerTool(self::tool('shopware-entity-read', 'Read one entity by ID'), 'Acme\\ReadTool', true);

        return $registry;
    }

    private static function tool(string $name, string $description): Tool
    {
        return new Tool(
            name: $name,
            title: null,
            inputSchema: ['type' => 'object', 'properties' => [], 'required' => []],
            description: $description,
            annotations: null,
        );
    }
}
