<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\AllowList;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistFilter;

/**
 * @internal
 */
#[CoversClass(McpAllowlistFilter::class)]
class McpAllowlistFilterTest extends TestCase
{
    private McpAllowlistFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new McpAllowlistFilter();
    }

    // ── Tools ────────────────────────────────────────────────────────────────

    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function toolCallDeniedProvider(): iterable
    {
        yield 'tool in allowlist is not denied' => ['shopware-entity-search', ['shopware-entity-search', 'shopware-entity-read'], false];
        yield 'tool not in allowlist is denied' => ['shopware-entity-delete', ['shopware-entity-search'], true];
        yield 'empty allowlist denies everything' => ['any-tool', [], true];
        yield 'exact name match required' => ['shopware-entity', ['shopware-entity-search'], true];
    }

    /**
     * @param list<string> $allowlist
     */
    #[DataProvider('toolCallDeniedProvider')]
    public function testIsToolCallDenied(string $toolName, array $allowlist, bool $expectedDenied): void
    {
        static::assertSame($expectedDenied, $this->filter->isToolCallDenied($toolName, $allowlist));
    }

    public function testFilterToolsListResponseKeepsAllowedTools(): void
    {
        $responseData = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'tools' => [
                    ['name' => 'tool-a', 'description' => 'A'],
                    ['name' => 'tool-b', 'description' => 'B'],
                    ['name' => 'tool-c', 'description' => 'C'],
                ],
            ],
        ];

        $filtered = $this->filter->filterToolsListResponse($responseData, ['tool-a', 'tool-c']);

        $names = array_column($filtered['result']['tools'], 'name');
        static::assertSame(['tool-a', 'tool-c'], $names);
    }

    public function testFilterToolsListResponseWithEmptyAllowlistRemovesAllTools(): void
    {
        $responseData = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'tools' => [
                    ['name' => 'tool-a'],
                ],
            ],
        ];

        $filtered = $this->filter->filterToolsListResponse($responseData, []);

        static::assertSame([], $filtered['result']['tools']);
    }

    public function testFilterToolsListResponseReindexesArray(): void
    {
        $responseData = [
            'result' => [
                'tools' => [
                    ['name' => 'tool-a'],
                    ['name' => 'tool-b'],
                ],
            ],
        ];

        $filtered = $this->filter->filterToolsListResponse($responseData, ['tool-b']);

        static::assertArrayHasKey(0, $filtered['result']['tools']);
        static::assertSame('tool-b', $filtered['result']['tools'][0]['name']);
    }

    public function testFilterToolsListResponsePassesThroughWhenNoToolsKey(): void
    {
        $responseData = ['jsonrpc' => '2.0', 'id' => 1, 'result' => ['nextCursor' => null]];

        $filtered = $this->filter->filterToolsListResponse($responseData, ['tool-a']);

        static::assertSame($responseData, $filtered);
    }

    public function testFilterToolsListResponsePassesThroughWhenToolsIsNotArray(): void
    {
        $responseData = ['result' => ['tools' => 'invalid']];

        $filtered = $this->filter->filterToolsListResponse($responseData, ['tool-a']);

        static::assertSame($responseData, $filtered);
    }

    // ── Resources ────────────────────────────────────────────────────────────

    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function resourceReadDeniedProvider(): iterable
    {
        yield 'resource in allowlist is not denied' => ['shopware://entities', ['shopware://entities', 'shopware://currencies'], false];
        yield 'resource not in allowlist is denied' => ['shopware://state-machines', ['shopware://entities'], true];
        yield 'empty allowlist denies everything' => ['shopware://entities', [], true];
        yield 'tool-result URI is never denied even with empty allowlist' => ['shopware://tool-result/abc123', [], false];
        yield 'tool-result URI is never denied when not in allowlist' => ['shopware://tool-result/xyz', ['shopware://entities'], false];
    }

    /**
     * @param list<string> $allowlist
     */
    #[DataProvider('resourceReadDeniedProvider')]
    public function testIsResourceReadDenied(string $uri, array $allowlist, bool $expectedDenied): void
    {
        static::assertSame($expectedDenied, $this->filter->isResourceReadDenied($uri, $allowlist));
    }

    public function testFilterResourcesListResponseKeepsAllowedResources(): void
    {
        $responseData = [
            'result' => [
                'resources' => [
                    ['uri' => 'shopware://entities', 'name' => 'Entities'],
                    ['uri' => 'shopware://currencies', 'name' => 'Currencies'],
                    ['uri' => 'shopware://state-machines', 'name' => 'State Machines'],
                ],
            ],
        ];

        $filtered = $this->filter->filterResourcesListResponse($responseData, ['shopware://entities', 'shopware://currencies']);

        $uris = array_column($filtered['result']['resources'], 'uri');
        static::assertSame(['shopware://entities', 'shopware://currencies'], $uris);
    }

    public function testFilterResourcesListResponseWithEmptyAllowlistRemovesAll(): void
    {
        $responseData = [
            'result' => [
                'resources' => [
                    ['uri' => 'shopware://entities'],
                ],
            ],
        ];

        $filtered = $this->filter->filterResourcesListResponse($responseData, []);

        static::assertSame([], $filtered['result']['resources']);
    }

    public function testFilterResourcesListResponsePassesThroughWhenNoResourcesKey(): void
    {
        $responseData = ['result' => ['nextCursor' => null]];

        $filtered = $this->filter->filterResourcesListResponse($responseData, ['shopware://entities']);

        static::assertSame($responseData, $filtered);
    }

    public function testFilterResourcesListResponseReindexesArray(): void
    {
        $responseData = [
            'result' => [
                'resources' => [
                    ['uri' => 'shopware://entities'],
                    ['uri' => 'shopware://currencies'],
                ],
            ],
        ];

        $filtered = $this->filter->filterResourcesListResponse($responseData, ['shopware://currencies']);

        static::assertArrayHasKey(0, $filtered['result']['resources']);
        static::assertSame('shopware://currencies', $filtered['result']['resources'][0]['uri']);
    }

    // ── Prompts ──────────────────────────────────────────────────────────────

    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function promptGetDeniedProvider(): iterable
    {
        yield 'prompt in allowlist is not denied' => ['shopware-context', ['shopware-context'], false];
        yield 'prompt not in allowlist is denied' => ['other-prompt', ['shopware-context'], true];
        yield 'empty allowlist denies everything' => ['shopware-context', [], true];
    }

    /**
     * @param list<string> $allowlist
     */
    #[DataProvider('promptGetDeniedProvider')]
    public function testIsPromptGetDenied(string $promptName, array $allowlist, bool $expectedDenied): void
    {
        static::assertSame($expectedDenied, $this->filter->isPromptGetDenied($promptName, $allowlist));
    }

    public function testFilterPromptsListResponseKeepsAllowedPrompts(): void
    {
        $responseData = [
            'result' => [
                'prompts' => [
                    ['name' => 'shopware-context', 'description' => 'Context'],
                    ['name' => 'shopware-developer', 'description' => 'Dev'],
                ],
            ],
        ];

        $filtered = $this->filter->filterPromptsListResponse($responseData, ['shopware-context']);

        $names = array_column($filtered['result']['prompts'], 'name');
        static::assertSame(['shopware-context'], $names);
    }

    public function testFilterPromptsListResponseWithEmptyAllowlistRemovesAll(): void
    {
        $responseData = [
            'result' => [
                'prompts' => [
                    ['name' => 'shopware-context'],
                ],
            ],
        ];

        $filtered = $this->filter->filterPromptsListResponse($responseData, []);

        static::assertSame([], $filtered['result']['prompts']);
    }

    public function testFilterPromptsListResponsePassesThroughWhenNoPromptsKey(): void
    {
        $responseData = ['result' => ['nextCursor' => null]];

        $filtered = $this->filter->filterPromptsListResponse($responseData, ['shopware-context']);

        static::assertSame($responseData, $filtered);
    }

    public function testFilterPromptsListResponseReindexesArray(): void
    {
        $responseData = [
            'result' => [
                'prompts' => [
                    ['name' => 'shopware-context'],
                    ['name' => 'shopware-developer'],
                ],
            ],
        ];

        $filtered = $this->filter->filterPromptsListResponse($responseData, ['shopware-developer']);

        static::assertArrayHasKey(0, $filtered['result']['prompts']);
        static::assertSame('shopware-developer', $filtered['result']['prompts'][0]['name']);
    }
}
