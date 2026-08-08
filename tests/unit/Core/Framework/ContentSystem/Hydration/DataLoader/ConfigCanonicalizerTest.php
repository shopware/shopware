<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ConfigCanonicalizer::class)]
class ConfigCanonicalizerTest extends TestCase
{
    private ConfigCanonicalizer $canonicalizer;

    protected function setUp(): void
    {
        $this->canonicalizer = new ConfigCanonicalizer();
    }

    /**
     * @return iterable<string, array{array<int|string, mixed>, array<int|string, mixed>}>
     */
    public static function canonicalizesProvider(): iterable
    {
        yield 'flat map' => [['limit' => 5, 'associations' => 'media'], ['associations' => 'media', 'limit' => 5]];
        yield 'list values' => [['associations' => ['media', 'manufacturer']], ['associations' => ['manufacturer', 'media']]];
        yield 'nested map' => [['filters' => ['status' => 'active', 'limit' => 10]], ['filters' => ['limit' => 10, 'status' => 'active']]];
        yield 'empty config' => [[], []];
        yield 'list of maps' => [
            ['items' => [['name' => 'zebra', 'id' => 2], ['name' => 'apple', 'id' => 1]]],
            ['items' => [['id' => 1, 'name' => 'apple'], ['id' => 2, 'name' => 'zebra']]],
        ];
        yield 'deeply nested maps sort at every level' => [
            ['config' => ['zebra' => 1, 'apple' => ['yak' => 2, 'ant' => 3]]],
            ['config' => ['apple' => ['ant' => 3, 'yak' => 2], 'zebra' => 1]],
        ];
    }

    /**
     * @param array<int|string, mixed> $input
     * @param array<int|string, mixed> $expected
     */
    #[DataProvider('canonicalizesProvider')]
    #[TestDox('canonicalizes: $_dataName')]
    public function testCanonicalizes(array $input, array $expected): void
    {
        static::assertSame($expected, $this->canonicalizer->canonicalize($input));
    }

    #[TestDox('produces the identical canonical shape for two configs differing only in key and list order')]
    public function testTwoConfigsDifferingOnlyInOrderCanonicalizeEqual(): void
    {
        $first = [
            'associations' => ['media', 'manufacturer'],
            'filters' => ['status' => 'active', 'limit' => 10],
        ];
        $second = [
            'filters' => ['limit' => 10, 'status' => 'active'],
            'associations' => ['manufacturer', 'media'],
        ];

        static::assertSame(
            $this->canonicalizer->canonicalize($first),
            $this->canonicalizer->canonicalize($second)
        );
    }
}
