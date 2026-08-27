<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Context\Distribution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\SlicedDistributionConfig;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('framework')]
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

    #[TestDox('distributes one item per consumer for a slice size of 1')]
    public function testDistributeChunksOneItemPerSliceForSliceSizeOne(): void
    {
        $config = SlicedDistributionConfig::withSliceSize(1);

        $consumers = [
            ['component' => 'box', 'properties' => []],
            ['component' => 'box', 'properties' => []],
            ['component' => 'box', 'properties' => []],
        ];

        $result = $config->distribute(['a', 'b', 'c'], $consumers);

        static::assertSame([['a'], ['b'], ['c']], $result);
    }

    #[TestDox('pins chunk boundaries for a slice size of 2 over an item count not a multiple of it')]
    public function testDistributeChunkBoundariesForSliceSizeTwoWithRemainder(): void
    {
        $config = SlicedDistributionConfig::withSliceSize(2);

        $consumers = [
            ['component' => 'box', 'properties' => []],
            ['component' => 'box', 'properties' => []],
            ['component' => 'box', 'properties' => []],
        ];

        $result = $config->distribute(['a', 'b', 'c', 'd', 'e'], $consumers);

        static::assertSame([['a', 'b'], ['c', 'd'], ['e']], $result);
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

    #[TestDox('serializes to array and deserializes back without data loss')]
    public function testFromArrayToArrayRoundtrip(): void
    {
        $original = [
            'distribution' => 'sliced',
            'sliceSize' => 5,
            'consumerAlias' => 'my-alias',
        ];

        $config = SlicedDistributionConfig::fromArray($original);

        static::assertSame($original, $config->toArray());
    }

    #[TestDox('falls back to the default sliceSize when the camelCase key is absent (legacy snake_case slice_size is ignored)')]
    public function testFromArrayFallsBackToDefaultSliceSizeWhenCamelCaseKeyAbsent(): void
    {
        $config = SlicedDistributionConfig::fromArray(['distribution' => 'sliced', 'slice_size' => 99]);

        static::assertSame(
            ['distribution' => 'sliced', 'sliceSize' => 10, 'consumerAlias' => null],
            $config->toArray()
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>, \Exception}>
     */
    public static function invalidFieldTypeProvider(): iterable
    {
        yield 'sliceSize non-int' => [
            ['distribution' => 'sliced', 'sliceSize' => '10'],
            ContentSystemException::invalidFieldValueType('sliceSize', 'int', 'string'),
        ];
        yield 'sliceSize null' => [
            ['distribution' => 'sliced', 'sliceSize' => null],
            ContentSystemException::invalidFieldValueType('sliceSize', 'int', 'null'),
        ];
        yield 'consumerAlias non-string' => [
            ['distribution' => 'sliced', 'sliceSize' => 5, 'consumerAlias' => 42],
            ContentSystemException::invalidFieldValueType('consumerAlias', 'string', 'int'),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidFieldTypeProvider')]
    #[TestDox('rejects invalid field type: $_dataName')]
    public function testRejectsInvalidFieldType(array $data, \Exception $exception): void
    {
        $this->expectExceptionObject($exception);

        SlicedDistributionConfig::fromArray($data);
    }

    #[TestDox('accepts a present sliceSize of exactly 1')]
    public function testFromArrayAcceptsSliceSizeOfOne(): void
    {
        $config = SlicedDistributionConfig::fromArray(['distribution' => 'sliced', 'sliceSize' => 1]);

        static::assertInstanceOf(SlicedDistributionConfig::class, $config);
        static::assertSame(1, $config->sliceSize);
    }

    #[DataProvider('rejectsSubOneSliceSizeProvider')]
    #[TestDox('rejects a present sliceSize below 1 instead of clamping it')]
    public function testFromArrayRejectsASubOneSliceSize(int $sliceSize): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueRange('sliceSize', 1, $sliceSize)
        );

        SlicedDistributionConfig::fromArray(['distribution' => 'sliced', 'sliceSize' => $sliceSize]);
    }

    #[DataProvider('rejectsSubOneSliceSizeProvider')]
    #[TestDox('rejects a sliceSize below 1 passed to withSliceSize() instead of clamping it')]
    public function testWithSliceSizeRejectsASubOneSliceSize(int $sliceSize): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueRange('sliceSize', 1, $sliceSize)
        );

        SlicedDistributionConfig::withSliceSize($sliceSize);
    }

    #[TestDox('returns constraint mapping with sliceSize NotBlank+Type(int)+GreaterThanOrEqual(1) and consumerAlias Type(string) constraints')]
    public function testBuildConstraintsReturnsExpectedConstraints(): void
    {
        $constraints = SlicedDistributionConfig::buildConstraints();

        static::assertArrayHasKey('sliceSize', $constraints);
        static::assertCount(3, $constraints['sliceSize']);
        static::assertInstanceOf(NotBlank::class, $constraints['sliceSize'][0]);
        static::assertInstanceOf(Type::class, $constraints['sliceSize'][1]);
        static::assertSame('int', $constraints['sliceSize'][1]->type);
        static::assertInstanceOf(GreaterThanOrEqual::class, $constraints['sliceSize'][2]);
        static::assertSame(1, $constraints['sliceSize'][2]->value);

        static::assertArrayHasKey('consumerAlias', $constraints);
        static::assertCount(1, $constraints['consumerAlias']);
        static::assertInstanceOf(Type::class, $constraints['consumerAlias'][0]);
        static::assertSame('string', $constraints['consumerAlias'][0]->type);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function rejectsSubOneSliceSizeProvider(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }
}
