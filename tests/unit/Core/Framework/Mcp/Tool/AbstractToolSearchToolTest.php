<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use Mcp\Capability\Registry;
use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlist;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopware\Core\Framework\Mcp\Tool\AbstractToolSearchTool;
use Shopware\Core\Framework\Mcp\Tool\Search\ToolSearch;
use Shopware\Core\Framework\Mcp\Tool\ToolSearchTool;

/**
 * @internal
 *
 * Exercises the shared search flow through ToolSearchTool, the lightest concrete subclass.
 */
#[Package('framework')]
#[CoversClass(AbstractToolSearchTool::class)]
class AbstractToolSearchToolTest extends TestCase
{
    public function testEmbeddedToolDefinitionKeepsEmptyPropertiesAsAnObject(): void
    {
        // The tool definition is embedded as JSON in the tool-call result; a parameterless tool's
        // empty properties must serialize as {} there too, or strict clients (OpenAI) reject the
        // whole payload with `[] is not of type 'object'`. The SDK `Tool` is embedded as it is and keeps
        // the empty properties as an object.
        $tool = new ToolSearchTool($this->registry(), new ToolSearch());

        $json = $tool('read entity');

        static::assertStringContainsString('"properties":{}', $json);
        static::assertStringNotContainsString('"properties":[]', $json);
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
        $registry->registerTool(self::tool('shopware-tool-search', 'Search tools'), 'Acme\\ToolSearch');

        $tool = new ToolSearchTool($registry, new ToolSearch());

        $data = json_decode($tool('tool search'), true, 512, \JSON_THROW_ON_ERROR);
        $names = array_column(array_column($data['data'], 'tool'), 'name');

        static::assertNotContains('shopware-tool-search', $names);
    }

    public function testReturnsErrorWhenRegistryIsUnavailable(): void
    {
        $tool = new ToolSearchTool(null, new ToolSearch());

        $data = json_decode($tool('entity'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertSame('MCP registry is not available.', $data['error']);
    }

    public function testSearchResultCountIsCappedAtTwenty(): void
    {
        $registry = new Registry();
        for ($i = 1; $i <= 25; ++$i) {
            $registry->registerTool(self::tool('shopware-entity-' . $i, 'Entity helper'), 'Acme\\Tool' . $i);
        }

        $tool = new ToolSearchTool($registry, new ToolSearch());

        $data = json_decode($tool('entity', 25), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(20, $data['data']);
        static::assertSame(25, $data['_meta']['totalCandidates']);
    }

    private function registry(): Registry
    {
        $registry = new Registry();
        $registry->registerTool(self::tool('shopware-entity-search', 'Search entities'), 'Acme\\SearchTool');
        $registry->registerTool(self::tool('shopware-entity-read', 'Read one entity by ID'), 'Acme\\ReadTool');

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
