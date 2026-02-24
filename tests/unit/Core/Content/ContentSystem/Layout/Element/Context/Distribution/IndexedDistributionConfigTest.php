<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Element\Context\Distribution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\IndexedDistributionConfig;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[CoversClass(IndexedDistributionConfig::class)]
class IndexedDistributionConfigTest extends TestCase
{
    /**
     * @param list<array{component: string, properties: array<string, mixed>}> $consumers
     * @param list<mixed> $expected
     */
    #[DataProvider('distributeProvider')]
    #[TestDox('distributes indexed data: $_dataName')]
    public function testDistribute(mixed $data, array $consumers, array $expected): void
    {
        $config = IndexedDistributionConfig::simple();

        $result = $config->distribute($data, $consumers);

        static::assertSame($expected, $result);
    }

    #[TestDox('round-trips through fromArray and toArray without data loss')]
    public function testFromArrayToArrayRoundtrip(): void
    {
        $original = [
            'distribution' => 'indexed',
            'consumer_alias' => 'my-alias',
        ];

        $config = IndexedDistributionConfig::fromArray($original);

        static::assertSame($original, $config->toArray());
    }

    #[TestDox('creates config with given alias via aliased factory')]
    public function testAliasedFactoryCreatesConfigWithAlias(): void
    {
        $config = IndexedDistributionConfig::aliased('my-alias');

        static::assertSame('my-alias', $config->getConsumerAlias());
    }

    #[TestDox('returns constraint mapping with consumer_alias string type constraint')]
    public function testBuildConstraintsReturnsExpectedConstraints(): void
    {
        $constraints = IndexedDistributionConfig::buildConstraints();

        static::assertArrayHasKey('consumer_alias', $constraints);
        static::assertCount(1, $constraints['consumer_alias']);
        static::assertInstanceOf(Type::class, $constraints['consumer_alias'][0]);
        static::assertSame('string', $constraints['consumer_alias'][0]->type);
    }

    /**
     * @return iterable<string, array{mixed, list<array{component: string, properties: array<string, mixed>}>, list<mixed>}>
     */
    public static function distributeProvider(): iterable
    {
        yield 'assigns data to consumer at matching position' => [
            ['alpha', 'beta'],
            [
                ['component' => 'product-box', 'properties' => []],
                ['component' => 'product-badge', 'properties' => []],
            ],
            ['alpha', 'beta'],
        ];

        yield 'returns null for consumers whose position exceeds the data length' => [
            ['only-one'],
            [
                ['component' => 'box', 'properties' => []],
                ['component' => 'box', 'properties' => []],
                ['component' => 'box', 'properties' => []],
            ],
            ['only-one', null, null],
        ];

        yield 'returns null for all consumers when data is not an array' => [
            'not-an-array',
            [
                ['component' => 'box', 'properties' => []],
                ['component' => 'box', 'properties' => []],
            ],
            [null, null],
        ];

        yield 'returns empty array when no consumers are given' => [
            ['item-a', 'item-b'],
            [],
            [],
        ];
    }
}
