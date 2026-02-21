<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Element\Context\Distribution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\IteratorDistributionConfig;

/**
 * @internal
 */
#[CoversClass(IteratorDistributionConfig::class)]
class IteratorDistributionConfigTest extends TestCase
{
    /**
     * @return \Generator<string, array{mixed}>
     */
    public static function nonArrayDataProvider(): \Generator
    {
        yield 'integer' => [42];
    }

    #[TestDox('returns array values directly when data is an array')]
    public function testDistributeReturnsDataValuesDirectly(): void
    {
        $config = IteratorDistributionConfig::simple();

        $result = $config->distribute(['a', 'b', 'c'], []);

        static::assertSame(['a', 'b', 'c'], $result);
    }

    #[DataProvider('nonArrayDataProvider')]
    #[TestDox('returns empty array when data is not an array')]
    public function testDistributeReturnsEmptyArrayWhenDataIsNotArray(mixed $data): void
    {
        $config = IteratorDistributionConfig::simple();
        static::assertSame([], $config->distribute($data, []));
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
}
