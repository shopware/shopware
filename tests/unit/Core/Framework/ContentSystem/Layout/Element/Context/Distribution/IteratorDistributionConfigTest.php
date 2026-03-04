<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Context\Distribution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IteratorDistributionConfig;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
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
            'consumer_alias' => 'my-alias',
        ];

        $config = IteratorDistributionConfig::fromArray($data);

        static::assertSame($data, $config->toArray());
    }

    #[TestDox('creates config with given alias via aliased factory')]
    public function testAliasedFactoryCreatesConfigWithAlias(): void
    {
        $config = IteratorDistributionConfig::aliased('my-alias');

        static::assertSame('my-alias', $config->getConsumerAlias());
    }

    #[TestDox('returns constraint mapping with consumer_alias string type constraint')]
    public function testReturnsConsumerAliasStringTypeConstraint(): void
    {
        $constraints = IteratorDistributionConfig::buildConstraints();

        static::assertArrayHasKey('consumer_alias', $constraints);
        static::assertCount(1, $constraints['consumer_alias']);
        static::assertInstanceOf(Type::class, $constraints['consumer_alias'][0]);
        static::assertSame('string', $constraints['consumer_alias'][0]->type);
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
