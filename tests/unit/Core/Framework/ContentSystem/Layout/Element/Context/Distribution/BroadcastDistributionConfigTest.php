<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Context\Distribution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('framework')]
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

    #[TestDox('creates config with null alias when consumerAlias is absent from array data')]
    public function testFromArrayWithoutConsumerAlias(): void
    {
        $config = BroadcastDistributionConfig::fromArray([
            'distribution' => 'broadcast',
        ]);

        static::assertNull($config->getConsumerAlias());
    }

    #[TestDox('rejects a present consumerAlias of the wrong type instead of substituting the default')]
    public function testFromArrayRejectsANonStringConsumerAlias(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('consumerAlias', 'string', 'int')
        );

        BroadcastDistributionConfig::fromArray([
            'distribution' => 'broadcast',
            'consumerAlias' => 42,
        ]);
    }

    #[TestDox('returns constraint mapping with consumerAlias string type constraint')]
    public function testBuildConstraintsReturnsExpectedConstraints(): void
    {
        $constraints = BroadcastDistributionConfig::buildConstraints();

        static::assertArrayHasKey('consumerAlias', $constraints);
        static::assertCount(1, $constraints['consumerAlias']);
        static::assertInstanceOf(Type::class, $constraints['consumerAlias'][0]);
        static::assertSame('string', $constraints['consumerAlias'][0]->type);
    }
}
