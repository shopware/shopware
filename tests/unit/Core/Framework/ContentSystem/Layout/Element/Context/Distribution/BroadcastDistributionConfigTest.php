<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Context\Distribution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
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

    #[TestDox('duplicates data to all consumers')]
    public function testDistributeCreatesCorrectNumberOfSlots(): void
    {
        $consumerCount = 3;
        $config = BroadcastDistributionConfig::simple();
        $data = ['value' => 'test'];
        $consumers = array_fill(0, $consumerCount, ['component' => 'foo', 'properties' => []]);

        $result = $config->distribute($data, $consumers);

        static::assertSame(array_fill(0, $consumerCount, $data), $result);
    }

    #[TestDox('returns an empty array when no consumers are given')]
    public function testDistributeWithEmptyConsumersReturnsEmptyArray(): void
    {
        $config = BroadcastDistributionConfig::simple();

        $result = $config->distribute('anything', []);

        static::assertSame([], $result);
    }

    #[TestDox('serializes to array and deserializes back without data loss')]
    public function testFromArrayToArrayRoundtrip(): void
    {
        $original = BroadcastDistributionConfig::aliased('round-trip-alias');

        $restored = BroadcastDistributionConfig::fromArray($original->toArray());

        static::assertSame('round-trip-alias', $restored->getConsumerAlias());
        static::assertSame($original->toArray(), $restored->toArray());
    }

    #[TestDox('creates config with null alias when consumer_alias is absent from array data')]
    public function testFromArrayWithoutConsumerAlias(): void
    {
        $config = BroadcastDistributionConfig::fromArray([
            'distribution' => 'broadcast',
        ]);

        static::assertNull($config->getConsumerAlias());
    }

    #[TestDox('creates config with null alias when consumer_alias is non-string in array data')]
    public function testFromArrayWithNonStringConsumerAlias(): void
    {
        $config = BroadcastDistributionConfig::fromArray([
            'distribution' => 'broadcast',
            'consumer_alias' => 42,
        ]);

        static::assertNull($config->getConsumerAlias());
    }

    #[TestDox('returns constraint mapping with consumer_alias string type constraint')]
    public function testBuildConstraintsReturnsExpectedConstraints(): void
    {
        $constraints = BroadcastDistributionConfig::buildConstraints();

        static::assertArrayHasKey('consumer_alias', $constraints);
        static::assertCount(1, $constraints['consumer_alias']);
        static::assertInstanceOf(Type::class, $constraints['consumer_alias'][0]);
        static::assertSame('string', $constraints['consumer_alias'][0]->type);
    }
}
