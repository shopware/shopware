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
     * @param list<string> $tools
     * @param list<string> $resources
     * @param list<string> $prompts
     */
    #[DataProvider('restrictedFromJsonProvider')]
    public function testRestrictedFromJson(?string $json, array $tools, array $resources, array $prompts): void
    {
        $allowlist = McpAllowlist::restrictedFromJson($json);

        static::assertSame($tools, $allowlist->tools);
        static::assertSame($resources, $allowlist->resources);
        static::assertSame($prompts, $allowlist->prompts);
    }

    /**
     * Anything that is not an explicit list of names resolves to an empty selection, so there is no
     * path back to unrestricted access for a principal without the administrator bypass.
     *
     * @return iterable<string, array{string|null, list<string>, list<string>, list<string>}>
     */
    public static function restrictedFromJsonProvider(): iterable
    {
        yield 'null column' => [null, [], [], []];
        yield 'empty column' => ['', [], [], []];
        yield 'unparseable JSON' => ['{not-valid-json}', [], [], []];
        yield 'JSON that is not an object' => ['"just-a-string"', [], [], []];
        yield 'empty object' => ['{}', [], [], []];

        yield 'explicit selection per type' => [
            '{"tools":["tool-a"],"resources":["shopware://entities"],"prompts":["shopware-context"]}',
            ['tool-a'],
            ['shopware://entities'],
            ['shopware-context'],
        ];
        yield 'explicit null per type' => [
            '{"tools":["tool-a"],"resources":null,"prompts":null}',
            ['tool-a'],
            [],
            [],
        ];
        yield 'per-type value is a string' => ['{"tools":"not-an-array"}', [], [], []];

        // json_decode(..., true) turns a JSON object into an associative array. It is not a list of
        // capability names, so reading its values as one would hand out capabilities nobody listed.
        yield 'per-type value is an object' => ['{"tools":{"x":"shopware-entity-delete"}}', [], [], []];
        yield 'per-type value is a sparse list' => ['{"tools":{"0":"tool-a","2":"tool-b"}}', [], [], []];

        yield 'non-string entries are dropped' => [
            '{"tools":["valid-tool",123,null,"another-tool"]}',
            ['valid-tool', 'another-tool'],
            [],
            [],
        ];
    }
}
