<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use Mcp\Capability\Registry;
use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\Search\ToolSearch;
use Shopware\Core\Framework\Mcp\Tool\ToolSearchTool;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ToolSearchTool::class)]
class ToolSearchToolTest extends TestCase
{
    public function testSearchReturnsMatchingToolDefinitions(): void
    {
        $tool = new ToolSearchTool($this->registry(), new ToolSearch());

        $data = $tool('read entity');

        static::assertFalse($data->isError());
        static::assertIsArray($data->data);
        $match = $data->data[0]['tool'];
        static::assertInstanceOf(Tool::class, $match);
        static::assertSame('shopware-entity-read', $match->name);
        static::assertSame(2, $data->meta['totalCandidates']);
    }

    public function testResultCarriesToolsetEnableUsageHint(): void
    {
        $tool = new ToolSearchTool($this->registry(), new ToolSearch());

        $data = $tool('read entity');

        static::assertArrayHasKey('usage', $data->meta);
        static::assertStringContainsString('shopware-toolset-enable', $data->meta['usage']);
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
