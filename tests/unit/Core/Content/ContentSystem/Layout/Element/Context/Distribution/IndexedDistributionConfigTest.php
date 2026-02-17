<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Element\Context\Distribution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\IndexedDistributionConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(IndexedDistributionConfig::class)]
class IndexedDistributionConfigTest extends TestCase
{
    #[TestDox('assigns data to consumer at matching position')]
    public function testDistributeAssignsDataByPosition(): void
    {
        $config = IndexedDistributionConfig::simple();

        $consumers = [
            ['component' => 'product-box', 'properties' => []],
            ['component' => 'product-badge', 'properties' => []],
        ];

        $result = $config->distribute(['alpha', 'beta'], $consumers);

        static::assertSame('alpha', $result[0]);
        static::assertSame('beta', $result[1]);
    }

    #[TestDox('returns null for consumers whose position exceeds the data length')]
    public function testDistributeReturnsNullForConsumersExceedingDataLength(): void
    {
        $config = IndexedDistributionConfig::simple();

        $consumers = [
            ['component' => 'box', 'properties' => []],
            ['component' => 'box', 'properties' => []],
            ['component' => 'box', 'properties' => []],
        ];

        $result = $config->distribute(['only-one'], $consumers);

        static::assertSame('only-one', $result[0]);
        static::assertNull($result[1]);
        static::assertNull($result[2]);
    }

    #[TestDox('returns null for all consumers when data is not an array')]
    public function testDistributeReturnsNullForAllConsumersWhenDataIsNotArray(): void
    {
        $config = IndexedDistributionConfig::simple();

        $consumers = [
            ['component' => 'box', 'properties' => []],
            ['component' => 'box', 'properties' => []],
        ];

        $result = $config->distribute('not-an-array', $consumers);

        static::assertCount(2, $result);
        static::assertNull($result[0]);
        static::assertNull($result[1]);
    }

    #[TestDox('returns an empty array when no consumers are given')]
    public function testDistributeWithEmptyConsumersReturnsEmptyArray(): void
    {
        $config = IndexedDistributionConfig::simple();

        $result = $config->distribute(['item-a', 'item-b'], []);

        static::assertSame([], $result);
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
}
