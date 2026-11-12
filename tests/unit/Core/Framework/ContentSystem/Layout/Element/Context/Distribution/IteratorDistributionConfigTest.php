<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Context\Distribution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IteratorDistributionConfig;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(IteratorDistributionConfig::class)]
class IteratorDistributionConfigTest extends TestCase
{
    #[TestDox('returns array values directly when data is an array')]
    public function testDistributeReturnsDataValuesDirectly(): void
    {
        $config = IteratorDistributionConfig::simple();

        $result = $config->distribute(['a', 'b', 'c'], []);

        static::assertSame(['a', 'b', 'c'], $result);
    }

    #[TestDox('produces same data via fromArray and toArray roundtrip')]
    public function testFromArrayToArrayRoundtrip(): void
    {
        $data = [
            'distribution' => 'iterator',
            'consumerAlias' => 'my-alias',
        ];

        $config = IteratorDistributionConfig::fromArray($data);

        static::assertSame($data, $config->toArray());
    }

    #[TestDox('takes the null default when consumerAlias is absent from the array data')]
    public function testFromArrayWithoutConsumerAliasTakesTheDefault(): void
    {
        $config = IteratorDistributionConfig::fromArray(['distribution' => 'iterator']);

        static::assertNull($config->getConsumerAlias());
    }

    #[TestDox('rejects a present consumerAlias of the wrong type instead of substituting the default')]
    public function testFromArrayRejectsANonStringConsumerAlias(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('consumerAlias', 'string', 'int')
        );

        IteratorDistributionConfig::fromArray(['distribution' => 'iterator', 'consumerAlias' => 42]);
    }

    #[TestDox('creates config with given alias via aliased factory')]
    public function testAliasedFactoryCreatesConfigWithAlias(): void
    {
        $config = IteratorDistributionConfig::aliased('my-alias');

        static::assertSame('my-alias', $config->getConsumerAlias());
    }

    #[TestDox('returns constraint mapping with consumerAlias string type constraint')]
    public function testReturnsConsumerAliasStringTypeConstraint(): void
    {
        $constraints = IteratorDistributionConfig::buildConstraints();

        static::assertArrayHasKey('consumerAlias', $constraints);
        static::assertCount(1, $constraints['consumerAlias']);
        static::assertInstanceOf(Type::class, $constraints['consumerAlias'][0]);
        static::assertSame('string', $constraints['consumerAlias'][0]->type);
    }

    #[DataProvider('distributeNonArrayProvider')]
    #[TestDox('returns empty array when data is not an array')]
    public function testDistributeReturnsEmptyArrayWhenDataIsNotArray(mixed $data): void
    {
        $config = IteratorDistributionConfig::simple();
        static::assertSame([], $config->distribute($data, []));
    }

    /**
     * @return \Generator<string, array{mixed}>
     */
    public static function distributeNonArrayProvider(): \Generator
    {
        yield 'integer' => [42];
    }
}
