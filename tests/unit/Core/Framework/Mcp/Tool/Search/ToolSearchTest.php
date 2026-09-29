<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool\Search;

use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\Search\ToolSearch;
use Shopware\Core\Framework\Mcp\Tool\Search\ToolSearchResult;

/**
 * @internal
 */
#[Package('framework')]
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

    public function testSearchHandlesToolWithoutDescriptionOrInputProperties(): void
    {
        $search = new ToolSearch();

        $results = $search->search([
            new Tool(
                name: 'entity-read',
                title: null,
                inputSchema: ['type' => 'object'], // @phpstan-ignore argument.type (malformed schema covers defensive fallback)
                description: null,
                annotations: null,
            ),
        ], 'entity', 10);

        static::assertNotEmpty($results);
        static::assertSame('entity-read', $results[0]->tool->name);
    }

    public function testFindsExactMultiTokenName(): void
    {
        $search = new ToolSearch();

        $results = $search->search([
            self::tool('order_state', 'Change order state'),
        ], 'order state', 10);

        static::assertNotEmpty($results);
        static::assertSame('order_state', $results[0]->tool->name);
        static::assertContains('name:exact-tokens', $results[0]->matchedIn);
        static::assertContains('name:token', $results[0]->matchedIn);
    }

    public function testFindsByDescriptionToken(): void
    {
        $search = new ToolSearch();

        $results = $search->search([
            self::tool('state-transition', 'Cancel a customer order'),
        ], 'customer', 10);

        static::assertNotEmpty($results);
        static::assertSame('state-transition', $results[0]->tool->name);
        static::assertContains('description:token', $results[0]->matchedIn);
    }

    public function testIgnoresToolsBelowMinimumScore(): void
    {
        $search = new ToolSearch();

        static::assertSame([], $search->search([
            self::tool('product-read', 'Read products'),
        ], 'shipping method', 10));
    }

    public function testMaxResultsLowerThanOneStillReturnsOneResult(): void
    {
        $search = new ToolSearch();

        $results = $search->search([
            self::tool('entity-search', 'Search entities'),
            self::tool('entity-read', 'Read entities'),
        ], 'entity', 0);

        static::assertCount(1, $results);
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
