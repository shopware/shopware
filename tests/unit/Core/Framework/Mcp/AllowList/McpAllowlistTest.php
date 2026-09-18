<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\AllowList;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testBlockedReturnsAllEmpty(): void
    {
        $allowlist = McpAllowlist::blocked();

        static::assertSame([], $allowlist->tools);
        static::assertSame([], $allowlist->resources);
        static::assertSame([], $allowlist->prompts);
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function unusableJsonProvider(): iterable
    {
        yield 'null column' => [null];
        yield 'empty string' => [''];
        yield 'invalid JSON' => ['{not-valid-json}'];
        yield 'scalar JSON' => ['"just-a-string"'];
    }

    #[DataProvider('unusableJsonProvider')]
    public function testRestrictedFromJsonBlocksEverythingForUnusableInput(?string $json): void
    {
        $allowlist = McpAllowlist::restrictedFromJson($json);

        static::assertSame([], $allowlist->tools);
        static::assertSame([], $allowlist->resources);
        static::assertSame([], $allowlist->prompts);
    }

    public function testRestrictedFromJsonKeepsExplicitSelections(): void
    {
        $allowlist = McpAllowlist::restrictedFromJson(
            '{"tools":["tool-a"],"resources":["shopware://entities"],"prompts":["shopware-context"]}'
        );

        static::assertSame(['tool-a'], $allowlist->tools);
        static::assertSame(['shopware://entities'], $allowlist->resources);
        static::assertSame(['shopware-context'], $allowlist->prompts);
    }

    public function testRestrictedFromJsonTreatsNullKeyAsBlockedInsteadOfUnrestricted(): void
    {
        $allowlist = McpAllowlist::restrictedFromJson('{"tools":["tool-a"],"resources":null,"prompts":null}');

        static::assertSame(['tool-a'], $allowlist->tools);
        static::assertSame([], $allowlist->resources);
        static::assertSame([], $allowlist->prompts);
    }

    public function testRestrictedFromJsonTreatsAbsentKeyAsBlockedInsteadOfUnrestricted(): void
    {
        $allowlist = McpAllowlist::restrictedFromJson('{}');

        static::assertSame([], $allowlist->tools);
        static::assertSame([], $allowlist->resources);
        static::assertSame([], $allowlist->prompts);
    }

    public function testRestrictedFromJsonTreatsWrongShapeAsBlockedInsteadOfUnrestricted(): void
    {
        $allowlist = McpAllowlist::restrictedFromJson('{"tools":"not-an-array"}');

        static::assertSame([], $allowlist->tools);
    }

    public function testRestrictedFromJsonFiltersNonStringValues(): void
    {
        $allowlist = McpAllowlist::restrictedFromJson('{"tools":["valid-tool",123,null,"another-tool"]}');

        static::assertSame(['valid-tool', 'another-tool'], $allowlist->tools);
    }
}
