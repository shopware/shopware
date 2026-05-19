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
        $responseData = self::toStdClass([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'tools' => [
                    ['name' => 'tool-a', 'description' => 'A'],
                    ['name' => 'tool-b', 'description' => 'B'],
                    ['name' => 'tool-c', 'description' => 'C'],
                ],
            ],
        ]);

        $filtered = $this->filter->filterToolsListResponse($responseData, ['tool-a', 'tool-c']);

        $result = $filtered->result;
        static::assertInstanceOf(\stdClass::class, $result);
        $names = array_column((array) $result->tools, 'name');
        static::assertSame(['tool-a', 'tool-c'], $names);
    }

    public function testFilterToolsListResponseWithEmptyAllowlistRemovesAllTools(): void
    {
        $responseData = self::toStdClass([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'tools' => [
                    ['name' => 'tool-a'],
                ],
            ],
        ]);

        $filtered = $this->filter->filterToolsListResponse($responseData, []);

        $result = $filtered->result;
        static::assertInstanceOf(\stdClass::class, $result);
        static::assertSame([], $result->tools);
    }

    public function testFilterToolsListResponseReindexesArray(): void
    {
        $responseData = self::toStdClass([
            'result' => [
                'tools' => [
                    ['name' => 'tool-a'],
                    ['name' => 'tool-b'],
                ],
            ],
        ]);

        $filtered = $this->filter->filterToolsListResponse($responseData, ['tool-b']);

        $result = $filtered->result;
        static::assertInstanceOf(\stdClass::class, $result);
        $tools = $result->tools;
        static::assertIsArray($tools);
        static::assertArrayHasKey(0, $tools);
        $firstTool = $tools[0];
        static::assertInstanceOf(\stdClass::class, $firstTool);
        static::assertSame('tool-b', $firstTool->name);
    }

    public function testFilterToolsListResponsePassesThroughWhenNoToolsKey(): void
    {
        $responseData = self::toStdClass(['jsonrpc' => '2.0', 'id' => 1, 'result' => ['nextCursor' => null]]);

        $filtered = $this->filter->filterToolsListResponse($responseData, ['tool-a']);

        static::assertEquals($responseData, $filtered);
    }

    public function testFilterToolsListResponsePassesThroughWhenToolsIsNotArray(): void
    {
        $responseData = self::toStdClass(['result' => ['tools' => 'invalid']]);

        $filtered = $this->filter->filterToolsListResponse($responseData, ['tool-a']);

        static::assertEquals($responseData, $filtered);
    }

    public function testFilterToolsListResponsePreservesInputSchemaObjects(): void
    {
        $responseData = json_decode(
            '{"result":{"tools":[{"name":"tool-a","inputSchema":{"type":"object","properties":{}}}]}}',
            false,
            512,
            \JSON_THROW_ON_ERROR,
        );
        static::assertInstanceOf(\stdClass::class, $responseData);

        $filtered = $this->filter->filterToolsListResponse($responseData, ['tool-a']);

        $json = json_encode($filtered, \JSON_THROW_ON_ERROR);
        static::assertStringContainsString('"properties":{}', $json, 'Empty properties must encode as {} not []');
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
        $responseData = self::toStdClass([
            'result' => [
                'resources' => [
                    ['uri' => 'shopware://entities', 'name' => 'Entities'],
                    ['uri' => 'shopware://currencies', 'name' => 'Currencies'],
                    ['uri' => 'shopware://state-machines', 'name' => 'State Machines'],
                ],
            ],
        ]);

        $filtered = $this->filter->filterResourcesListResponse($responseData, ['shopware://entities', 'shopware://currencies']);

        $result = $filtered->result;
        static::assertInstanceOf(\stdClass::class, $result);
        $uris = array_column((array) $result->resources, 'uri');
        static::assertSame(['shopware://entities', 'shopware://currencies'], $uris);
    }

    public function testFilterResourcesListResponseWithEmptyAllowlistRemovesAll(): void
    {
        $responseData = self::toStdClass([
            'result' => [
                'resources' => [
                    ['uri' => 'shopware://entities'],
                ],
            ],
        ]);

        $filtered = $this->filter->filterResourcesListResponse($responseData, []);

        $result = $filtered->result;
        static::assertInstanceOf(\stdClass::class, $result);
        static::assertSame([], $result->resources);
    }

    public function testFilterResourcesListResponsePassesThroughWhenNoResourcesKey(): void
    {
        $responseData = self::toStdClass(['result' => ['nextCursor' => null]]);

        $filtered = $this->filter->filterResourcesListResponse($responseData, ['shopware://entities']);

        static::assertEquals($responseData, $filtered);
    }

    public function testFilterResourcesListResponseReindexesArray(): void
    {
        $responseData = self::toStdClass([
            'result' => [
                'resources' => [
                    ['uri' => 'shopware://entities'],
                    ['uri' => 'shopware://currencies'],
                ],
            ],
        ]);

        $filtered = $this->filter->filterResourcesListResponse($responseData, ['shopware://currencies']);

        $result = $filtered->result;
        static::assertInstanceOf(\stdClass::class, $result);
        $resources = $result->resources;
        static::assertIsArray($resources);
        static::assertArrayHasKey(0, $resources);
        $firstResource = $resources[0];
        static::assertInstanceOf(\stdClass::class, $firstResource);
        static::assertSame('shopware://currencies', $firstResource->uri);
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
        $responseData = self::toStdClass([
            'result' => [
                'prompts' => [
                    ['name' => 'shopware-context', 'description' => 'Context'],
                    ['name' => 'shopware-developer', 'description' => 'Dev'],
                ],
            ],
        ]);

        $filtered = $this->filter->filterPromptsListResponse($responseData, ['shopware-context']);

        $result = $filtered->result;
        static::assertInstanceOf(\stdClass::class, $result);
        $names = array_column((array) $result->prompts, 'name');
        static::assertSame(['shopware-context'], $names);
    }

    public function testFilterPromptsListResponseWithEmptyAllowlistRemovesAll(): void
    {
        $responseData = self::toStdClass([
            'result' => [
                'prompts' => [
                    ['name' => 'shopware-context'],
                ],
            ],
        ]);

        $filtered = $this->filter->filterPromptsListResponse($responseData, []);

        $result = $filtered->result;
        static::assertInstanceOf(\stdClass::class, $result);
        static::assertSame([], $result->prompts);
    }

    public function testFilterPromptsListResponsePassesThroughWhenNoPromptsKey(): void
    {
        $responseData = self::toStdClass(['result' => ['nextCursor' => null]]);

        $filtered = $this->filter->filterPromptsListResponse($responseData, ['shopware-context']);

        static::assertEquals($responseData, $filtered);
    }

    public function testFilterPromptsListResponseReindexesArray(): void
    {
        $responseData = self::toStdClass([
            'result' => [
                'prompts' => [
                    ['name' => 'shopware-context'],
                    ['name' => 'shopware-developer'],
                ],
            ],
        ]);

        $filtered = $this->filter->filterPromptsListResponse($responseData, ['shopware-developer']);

        $result = $filtered->result;
        static::assertInstanceOf(\stdClass::class, $result);
        $prompts = $result->prompts;
        static::assertIsArray($prompts);
        static::assertArrayHasKey(0, $prompts);
        $firstPrompt = $prompts[0];
        static::assertInstanceOf(\stdClass::class, $firstPrompt);
        static::assertSame('shopware-developer', $firstPrompt->name);
    }

    public function testFilterToolsListResponsePassesThroughWhenResultIsNotStdClass(): void
    {
        $responseData = new \stdClass();
        $responseData->result = 'not-an-object';

        $filtered = $this->filter->filterToolsListResponse($responseData, ['tool-a']);

        static::assertSame($responseData, $filtered);
        static::assertSame('not-an-object', $filtered->result);
    }

    public function testFilterResourcesListResponsePassesThroughWhenResultIsNotStdClass(): void
    {
        $responseData = new \stdClass();
        $responseData->result = 'not-an-object';

        $filtered = $this->filter->filterResourcesListResponse($responseData, ['shopware://entities']);

        static::assertSame($responseData, $filtered);
        static::assertSame('not-an-object', $filtered->result);
    }

    public function testFilterPromptsListResponsePassesThroughWhenResultIsNotStdClass(): void
    {
        $responseData = new \stdClass();
        $responseData->result = 'not-an-object';

        $filtered = $this->filter->filterPromptsListResponse($responseData, ['shopware-context']);

        static::assertSame($responseData, $filtered);
        static::assertSame('not-an-object', $filtered->result);
    }

    /**
     * @param array<mixed> $data
     */
    private static function toStdClass(array $data): \stdClass
    {
        return json_decode(json_encode($data, \JSON_THROW_ON_ERROR), false, 512, \JSON_THROW_ON_ERROR);
    }
}
