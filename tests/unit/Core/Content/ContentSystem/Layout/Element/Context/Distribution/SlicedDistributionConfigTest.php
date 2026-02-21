<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Element\Context\Distribution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\SlicedDistributionConfig;

/**
 * @internal
 */
#[CoversClass(SlicedDistributionConfig::class)]
class SlicedDistributionConfigTest extends TestCase
{
    #[TestDox('distributes 6 items into 3 slices of 2 across 3 consumers')]
    public function testDistributeChunksDataEvenly(): void
    {
        $config = SlicedDistributionConfig::withSliceSize(2);

        $consumers = [
            ['component' => 'box', 'properties' => []],
            ['component' => 'box', 'properties' => []],
            ['component' => 'box', 'properties' => []],
        ];

        $result = $config->distribute([0, 1, 2, 3, 4, 5], $consumers);

        static::assertSame([[0, 1], [2, 3], [4, 5]], $result);
    }

    #[TestDox('distributes 7 items into slices of 3 yielding two full slices and one remainder')]
    public function testDistributeHandlesUnevenChunks(): void
    {
        $config = SlicedDistributionConfig::withSliceSize(3);

        $consumers = [
            ['component' => 'box', 'properties' => []],
            ['component' => 'box', 'properties' => []],
            ['component' => 'box', 'properties' => []],
        ];

        $result = $config->distribute([0, 1, 2, 3, 4, 5, 6], $consumers);

        static::assertSame([[0, 1, 2], [3, 4, 5], [6]], $result);
    }

    #[TestDox('returns empty array for consumers whose index exceeds available slices')]
    public function testDistributeReturnsEmptyArrayForConsumersBeyondAvailableSlices(): void
    {
        $config = SlicedDistributionConfig::withSliceSize(3);

        $consumers = [
            ['component' => 'box', 'properties' => []],
            ['component' => 'box', 'properties' => []],
            ['component' => 'box', 'properties' => []],
        ];

        // Only 3 items → 1 slice of 3; consumers at index 1 and 2 get no slice
        $result = $config->distribute([0, 1, 2], $consumers);

        static::assertSame([[0, 1, 2], [], []], $result);
    }

    #[TestDox('treats a slice size less than 1 as 1 and distributes one item per consumer')]
    public function testDistributeTreatsSliceSizeLessThanOneAsOne(): void
    {
        $config = SlicedDistributionConfig::withSliceSize(0);

        $consumers = [
            ['component' => 'box', 'properties' => []],
            ['component' => 'box', 'properties' => []],
            ['component' => 'box', 'properties' => []],
        ];

        $result = $config->distribute(['a', 'b', 'c'], $consumers);

        static::assertSame([['a'], ['b'], ['c']], $result);
    }

    #[TestDox('returns empty arrays for all consumers when data is not an array')]
    public function testDistributeReturnsEmptyArraysWhenDataIsNotArray(): void
    {
        $config = SlicedDistributionConfig::withSliceSize(2);

        $consumers = [
            ['component' => 'box', 'properties' => []],
            ['component' => 'box', 'properties' => []],
        ];

        $result = $config->distribute('not-an-array', $consumers);

        static::assertCount(2, $result);
        static::assertSame([], $result[0]);
        static::assertSame([], $result[1]);
    }

    #[TestDox('round-trips through fromArray and toArray without data loss')]
    public function testFromArrayToArrayRoundtrip(): void
    {
        $original = [
            'distribution' => 'sliced',
            'slice_size' => 5,
            'consumer_alias' => 'my-alias',
        ];

        $config = SlicedDistributionConfig::fromArray($original);

        static::assertSame($original, $config->toArray());
    }
}
