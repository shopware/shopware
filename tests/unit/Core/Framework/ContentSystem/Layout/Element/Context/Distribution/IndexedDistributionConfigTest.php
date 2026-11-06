<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Context\Distribution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IndexedDistributionConfig;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('framework')]
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
            'consumerAlias' => 'my-alias',
        ];

        $config = IndexedDistributionConfig::fromArray($original);

        static::assertSame($original, $config->toArray());
    }

    #[TestDox('takes the null default when consumerAlias is absent from the array data')]
    public function testFromArrayWithoutConsumerAliasTakesTheDefault(): void
    {
        $config = IndexedDistributionConfig::fromArray(['distribution' => 'indexed']);

        static::assertNull($config->getConsumerAlias());
    }

    #[TestDox('rejects a present consumerAlias of the wrong type instead of substituting the default')]
    public function testFromArrayRejectsANonStringConsumerAlias(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('consumerAlias', 'string', 'int')
        );

        IndexedDistributionConfig::fromArray(['distribution' => 'indexed', 'consumerAlias' => 42]);
    }

    #[TestDox('creates config with given alias via aliased factory')]
    public function testAliasedFactoryCreatesConfigWithAlias(): void
    {
        $config = IndexedDistributionConfig::aliased('my-alias');

        static::assertSame('my-alias', $config->getConsumerAlias());
    }

    #[TestDox('returns constraint mapping with consumerAlias string type constraint')]
    public function testBuildConstraintsReturnsExpectedConstraints(): void
    {
        $constraints = IndexedDistributionConfig::buildConstraints();

        static::assertArrayHasKey('consumerAlias', $constraints);
        static::assertCount(1, $constraints['consumerAlias']);
        static::assertInstanceOf(Type::class, $constraints['consumerAlias'][0]);
        static::assertSame('string', $constraints['consumerAlias'][0]->type);
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
