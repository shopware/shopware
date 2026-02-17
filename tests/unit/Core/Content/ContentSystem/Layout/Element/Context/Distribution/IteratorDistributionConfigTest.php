<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Element\Context\Distribution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\IteratorDistributionConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
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

    #[TestDox('returns empty array when data is not an array')]
    public function testDistributeReturnsEmptyArrayWhenDataIsNotArray(): void
    {
        $config = IteratorDistributionConfig::simple();

        static::assertSame([], $config->distribute('not-an-array', []));
        static::assertSame([], $config->distribute(42, []));
        static::assertSame([], $config->distribute(null, []));
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
