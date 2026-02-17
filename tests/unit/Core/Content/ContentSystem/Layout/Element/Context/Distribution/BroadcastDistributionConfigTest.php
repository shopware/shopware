<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Element\Context\Distribution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BroadcastDistributionConfig::class)]
class BroadcastDistributionConfigTest extends TestCase
{
    #[TestDox('creates config with null alias via simple factory')]
    public function testSimpleFactoryCreatesWithNullAlias(): void
    {
        $config = BroadcastDistributionConfig::simple();

        static::assertNull($config->getConsumerAlias());
        static::assertSame(DistributionStrategy::Broadcast, $config->getStrategy());
    }

    #[TestDox('creates config with the given alias via aliased factory')]
    public function testAliasedFactoryCreatesWithAlias(): void
    {
        $config = BroadcastDistributionConfig::aliased('my-alias');

        static::assertSame('my-alias', $config->getConsumerAlias());
        static::assertSame(DistributionStrategy::Broadcast, $config->getStrategy());
    }

    /**
     * @return \Generator<string, array{int}>
     */
    public static function consumerCountsProvider(): \Generator
    {
        yield '1 consumer' => [1];
        yield '3 consumers' => [3];
        yield '5 consumers' => [5];
    }

    #[DataProvider('consumerCountsProvider')]
    #[TestDox('duplicates data to all consumers')]
    public function testDistributeDuplicatesDataToAllConsumers(int $consumerCount): void
    {
        $config = BroadcastDistributionConfig::simple();
        $data = ['value' => 'test'];
        $consumers = array_fill(0, $consumerCount, ['component' => 'foo', 'properties' => []]);

        $result = $config->distribute($data, $consumers);

        static::assertCount($consumerCount, $result);
        foreach ($result as $distributed) {
            static::assertSame($data, $distributed);
        }
    }

    #[TestDox('returns an empty array when no consumers are given')]
    public function testDistributeWithEmptyConsumersReturnsEmptyArray(): void
    {
        $config = BroadcastDistributionConfig::simple();

        $result = $config->distribute('anything', []);

        static::assertSame([], $result);
    }

    #[TestDox('round-trips through fromArray and toArray without data loss')]
    public function testFromArrayToArrayRoundtrip(): void
    {
        $original = BroadcastDistributionConfig::aliased('round-trip-alias');

        $restored = BroadcastDistributionConfig::fromArray($original->toArray());

        static::assertSame($original->toArray(), $restored->toArray());
    }

    #[TestDox('creates config with consumer alias from array data')]
    public function testFromArrayWithConsumerAlias(): void
    {
        $config = BroadcastDistributionConfig::fromArray([
            'distribution' => 'broadcast',
            'consumer_alias' => 'some-alias',
        ]);

        static::assertSame('some-alias', $config->getConsumerAlias());
    }

    #[TestDox('creates config with null alias when consumer_alias is absent from array data')]
    public function testFromArrayWithoutConsumerAlias(): void
    {
        $config = BroadcastDistributionConfig::fromArray([
            'distribution' => 'broadcast',
        ]);

        static::assertNull($config->getConsumerAlias());
    }
}
