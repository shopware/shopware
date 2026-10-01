<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpToolSchemaNormalizer;
use Shopware\Core\Framework\Util\Json;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolSchemaNormalizer::class)]
class McpToolSchemaNormalizerTest extends TestCase
{
    public function testNormalizeToolListResultForcesEmptyPropertiesToAnObject(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['tools' => [['name' => 't', 'inputSchema' => ['type' => 'object', 'properties' => []]]]],
        ];

        $normalized = McpToolSchemaNormalizer::normalizeToolListResult($message);

        static::assertNotNull($normalized);
        static::assertStringContainsString('"properties":{}', Json::encode($normalized));
        static::assertStringNotContainsString('"properties":[]', Json::encode($normalized));
    }

    public function testNormalizeToolListResultForcesNestedAndOutputSchemaProperties(): void
    {
        $message = [
            'result' => ['tools' => [[
                'name' => 't',
                'inputSchema' => ['type' => 'object', 'properties' => ['filter' => ['type' => 'object', 'properties' => []]]],
                'outputSchema' => ['type' => 'object', 'properties' => []],
            ]]],
        ];

        $normalized = McpToolSchemaNormalizer::normalizeToolListResult($message);

        static::assertNotNull($normalized);
        static::assertStringNotContainsString('"properties":[]', Json::encode($normalized));
        static::assertSame(2, substr_count(Json::encode($normalized), '"properties":{}'));
    }

    public function testNormalizeToolListResultReturnsNullWhenNothingChanged(): void
    {
        $message = [
            'result' => ['tools' => [['name' => 't', 'inputSchema' => ['type' => 'object', 'properties' => ['q' => ['type' => 'string']]]]]],
        ];

        static::assertNull(McpToolSchemaNormalizer::normalizeToolListResult($message));
    }

    public function testNormalizeToolListResultReturnsNullWhenNotAToolList(): void
    {
        static::assertNull(McpToolSchemaNormalizer::normalizeToolListResult(['result' => []]));
        static::assertNull(McpToolSchemaNormalizer::normalizeToolListResult(['jsonrpc' => '2.0', 'method' => 'ping']));
    }

    public function testNormalizeToolListResultSkipsNonArrayToolEntries(): void
    {
        $message = [
            'result' => ['tools' => [
                'not-a-tool',
                ['name' => 't', 'inputSchema' => ['type' => 'object', 'properties' => []]],
            ]],
        ];

        $normalized = McpToolSchemaNormalizer::normalizeToolListResult($message);

        static::assertNotNull($normalized);
        static::assertSame('not-a-tool', $normalized['result']['tools'][0]);
        static::assertStringContainsString('"properties":{}', Json::encode($normalized['result']['tools'][1]));
    }

    public function testNormalizeToolAlwaysReturnsTheToolWithObjectProperties(): void
    {
        $tool = McpToolSchemaNormalizer::normalizeTool(['name' => 't', 'inputSchema' => ['type' => 'object', 'properties' => []]]);

        static::assertStringContainsString('"properties":{}', Json::encode($tool));
        static::assertStringNotContainsString('"properties":[]', Json::encode($tool));
    }

    public function testNormalizeToolLeavesPopulatedPropertiesUntouched(): void
    {
        $input = ['name' => 't', 'inputSchema' => ['type' => 'object', 'properties' => ['q' => ['type' => 'string']]]];

        static::assertSame($input, McpToolSchemaNormalizer::normalizeTool($input));
    }
}
