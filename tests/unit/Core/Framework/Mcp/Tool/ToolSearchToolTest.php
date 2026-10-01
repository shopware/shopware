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
 */
#[Package('framework')]
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

    public function testEmbeddedToolDefinitionKeepsEmptyPropertiesAsAnObject(): void
    {
        // The tool definition is embedded as JSON in the tool-call result; a parameterless tool's
        // empty properties must serialize as {} there too, or strict clients (OpenAI) reject the
        // whole payload with `[] is not of type 'object'`. The transport normalizer never sees this
        // location (it lives inside result.content[].text), so it is fixed at the source instead.
        $tool = new ToolSearchTool($this->registry(), new ToolSearch());

        $json = $tool('read entity');

        static::assertStringContainsString('"properties":{}', $json);
        static::assertStringNotContainsString('"properties":[]', $json);
    }

    public function testResultCarriesToolsetEnableUsageHint(): void
    {
        $tool = new ToolSearchTool($this->registry(), new ToolSearch());

        $data = json_decode($tool('read entity'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('usage', $data['_meta']);
        static::assertStringContainsString('shopware-toolset-enable', $data['_meta']['usage']);
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

    public function testInvokeIsDeclaredOnConcreteClassSoDiscoveryBindsToIt(): void
    {
        // The MCP SDK discoverer binds a tool handler to __invoke's declaring class. If __invoke
        // were only inherited from AbstractToolSearchTool, discovery would bind the handler to the
        // non-instantiable abstract base and the tool would fail at runtime.
        $method = new \ReflectionMethod(ToolSearchTool::class, '__invoke');

        static::assertSame(ToolSearchTool::class, $method->getDeclaringClass()->getName());
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
