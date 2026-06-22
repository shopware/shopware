<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\AllowList;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlist;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpAllowlist::class)]
class McpAllowlistTest extends TestCase
{
    public function testUnrestrictedReturnsAllNull(): void
    {
        $allowlist = McpAllowlist::unrestricted();

        static::assertNull($allowlist->tools);
        static::assertNull($allowlist->resources);
        static::assertNull($allowlist->prompts);
    }

    public function testFromJsonNullReturnsUnrestricted(): void
    {
        $allowlist = McpAllowlist::fromJson(null);

        static::assertNull($allowlist->tools);
        static::assertNull($allowlist->resources);
        static::assertNull($allowlist->prompts);
    }

    public function testFromJsonEmptyStringReturnsUnrestricted(): void
    {
        $allowlist = McpAllowlist::fromJson('');

        static::assertNull($allowlist->tools);
        static::assertNull($allowlist->resources);
        static::assertNull($allowlist->prompts);
    }

    public function testFromJsonInvalidJsonReturnsUnrestricted(): void
    {
        $allowlist = McpAllowlist::fromJson('not-valid-json');

        static::assertNull($allowlist->tools);
        static::assertNull($allowlist->resources);
        static::assertNull($allowlist->prompts);
    }

    public function testFromJsonScalarReturnsUnrestricted(): void
    {
        $allowlist = McpAllowlist::fromJson('"string"');

        static::assertNull($allowlist->tools);
        static::assertNull($allowlist->resources);
        static::assertNull($allowlist->prompts);
    }

    public function testFromJsonParsesToolsList(): void
    {
        $allowlist = McpAllowlist::fromJson('{"tools":["tool-a","tool-b"]}');

        static::assertSame(['tool-a', 'tool-b'], $allowlist->tools);
        static::assertNull($allowlist->resources);
        static::assertNull($allowlist->prompts);
    }

    public function testFromJsonParsesAllTypes(): void
    {
        $json = json_encode([
            'tools' => ['shopware-entity-read', 'shopware-entity-search'],
            'resources' => ['shopware://entities'],
            'prompts' => ['shopware-context'],
        ]);
        static::assertNotFalse($json);

        $allowlist = McpAllowlist::fromJson($json);

        static::assertSame(['shopware-entity-read', 'shopware-entity-search'], $allowlist->tools);
        static::assertSame(['shopware://entities'], $allowlist->resources);
        static::assertSame(['shopware-context'], $allowlist->prompts);
    }

    public function testFromJsonNullKeyMeansUnrestricted(): void
    {
        $allowlist = McpAllowlist::fromJson('{"tools":null,"resources":["shopware://entities"]}');

        static::assertNull($allowlist->tools);
        static::assertSame(['shopware://entities'], $allowlist->resources);
        static::assertNull($allowlist->prompts);
    }

    public function testFromJsonEmptyArrayBlocksEverything(): void
    {
        $allowlist = McpAllowlist::fromJson('{"tools":[],"resources":[],"prompts":[]}');

        static::assertSame([], $allowlist->tools);
        static::assertSame([], $allowlist->resources);
        static::assertSame([], $allowlist->prompts);
    }

    public function testFromJsonFiltersNonStringValues(): void
    {
        $allowlist = McpAllowlist::fromJson('{"tools":["valid-tool",123,null,"another-tool"]}');

        static::assertSame(['valid-tool', 'another-tool'], $allowlist->tools);
    }

    public function testFromJsonInvalidTypeForListReturnsNull(): void
    {
        $allowlist = McpAllowlist::fromJson('{"tools":"not-an-array"}');

        static::assertNull($allowlist->tools);
    }

    public function testFromJsonAbsentKeyReturnsNull(): void
    {
        $allowlist = McpAllowlist::fromJson('{}');

        static::assertNull($allowlist->tools);
        static::assertNull($allowlist->resources);
        static::assertNull($allowlist->prompts);
    }
}
