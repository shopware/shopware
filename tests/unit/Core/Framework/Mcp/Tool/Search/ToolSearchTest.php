<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool\Search;

use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\Tool\Search\ToolSearch;
use Shopware\Core\Framework\Mcp\Tool\Search\ToolSearchResult;

/**
 * @internal
 */
#[CoversClass(ToolSearch::class)]
#[CoversClass(ToolSearchResult::class)]
class ToolSearchTest extends TestCase
{
    public function testEmptyQueryReturnsNoResults(): void
    {
        $search = new ToolSearch();

        static::assertSame([], $search->search([self::tool('issue-list', 'List issues')], '   '));
    }

    public function testFindsByName(): void
    {
        $search = new ToolSearch();

        $results = $search->search([
            self::tool('issue-list', 'List issues'),
            self::tool('repo-get', 'Get repository'),
        ], 'issue', 10);

        static::assertNotEmpty($results);
        static::assertSame('issue-list', $results[0]->tool->name);
        static::assertContains('name:substring', $results[0]->matchedIn);
    }

    public function testFindsByParameterName(): void
    {
        $search = new ToolSearch();

        $results = $search->search([
            self::tool('unrelated-tool', 'does something else', ['owner' => ['type' => 'string']]),
        ], 'owner', 10);

        static::assertNotEmpty($results);
        static::assertSame('unrelated-tool', $results[0]->tool->name);
        static::assertContains('parameter', $results[0]->matchedIn);
    }

    public function testLimitsResultCount(): void
    {
        $search = new ToolSearch();

        $results = $search->search([
            self::tool('entity-search', 'Search entities'),
            self::tool('entity-read', 'Read entities'),
            self::tool('entity-delete', 'Delete entities'),
        ], 'entity', 2);

        static::assertCount(2, $results);
    }

    /**
     * @param array<string, mixed> $properties
     */
    private static function tool(string $name, string $description, array $properties = []): Tool
    {
        return new Tool(
            name: $name,
            title: null,
            inputSchema: ['type' => 'object', 'properties' => $properties, 'required' => []],
            description: $description,
            annotations: null,
        );
    }
}
