<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Codec;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\Log\Package;

/**
 * Both directions of the wire shape over a payload decode admits: what decode reads, encode writes back
 * unchanged. The three failure tiers sit in {@see StoredElementCodecStructuralDecodeTest},
 * {@see StoredElementWiringDecoderTest} and {@see StoredElementCodecDataRequirementTest}; all four share
 * {@see StoredElementCodecTestCase}.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredElementCodec::class)]
class StoredElementCodecTest extends StoredElementCodecTestCase
{
    /**
     * @param array<string, mixed> $wire
     */
    #[DataProvider('roundTripProvider')]
    #[TestDox('decode then encode returns $_dataName unchanged')]
    public function testRoundTrip(array $wire): void
    {
        $codec = $this->codec();

        static::assertSame($wire, $codec->encode($codec->decode($wire)));
    }

    #[TestDox('decode accepts an id that only looks numeric')]
    public function testDecodeAcceptsANonCastableNumericLookingId(): void
    {
        $element = $this->codec()->decode(['id' => '012', 'component' => 'core:text', 'properties' => []]);

        static::assertSame('012', $element->id);
    }

    /**
     * @param array<string, mixed> $wire
     */
    #[DataProvider('acceptsNestingAtTheLimitProvider')]
    #[TestDox('accepts $_dataName')]
    public function testDecodeAcceptsNestingAtTheLimit(array $wire): void
    {
        $codec = $this->codec();

        static::assertSame($wire, $codec->encode($codec->decode($wire)));
    }

    #[TestDox('decode treats an explicitly empty optional container as absent')]
    public function testDecodeTreatsAnExplicitlyEmptyOptionalContainerAsAbsent(): void
    {
        $wire = [
            'id' => 'el-1',
            'component' => 'core:text',
            'properties' => ['title' => 'Hello'],
            'dataRequirements' => [],
            'slots' => [],
            'providesContext' => [],
            'acceptsContext' => [],
            'style' => [],
            'attributedSpecifications' => [],
        ];

        $element = $this->codec()->decode($wire);

        static::assertSame([], $element->slots);
        static::assertSame([], $element->dataRequirements);
        static::assertSame([], $element->attributedSpecifications);
        static::assertTrue($element->style->isEmpty());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function roundTripProvider(): iterable
    {
        yield 'a minimal element' => [[
            'id' => 'el-1',
            'component' => 'core:text',
            'properties' => [],
        ]];

        yield 'an element carrying every field' => [[
            'id' => 'el-1',
            'component' => 'core:section',
            'properties' => [
                'title' => 'Hello',
                'count' => 3,
                'ratio' => 1.5,
                'flag' => true,
                'nothing' => null,
                'tags' => ['a', 'b'],
                'meta' => ['unit' => 'px'],
            ],
            'dataRequirements' => [
                'products' => [
                    'key' => 'products',
                    'source' => 'entity',
                    'config' => ['entity' => 'product', 'property' => 'productId'],
                ],
            ],
            'slots' => [
                'main' => [
                    ['id' => 'child-1', 'component' => 'core:text', 'properties' => []],
                ],
            ],
            'providesContext' => [
                'product' => [
                    'type' => 'single',
                    'distribution' => 'keyed',
                    'keyProperty' => 'sku',
                    'consumerAlias' => null,
                ],
            ],
            'acceptsContext' => [
                'items' => [
                    'type' => 'collection',
                    'required' => true,
                    'redistribute' => true,
                    'consumerAlias' => 'inner',
                    'propertyAlias' => 'entries',
                ],
            ],
            'style' => ['col-span' => ['md' => 6]],
            'attributedSpecifications' => ['image' => 'core:product-image'],
        ]];

        yield 'an element whose style mixes a flat option with a breakpoint map' => [self::baseWire([
            'style' => [
                'flat-option' => 'red',
                'breakpoint-option' => ['md' => 10],
            ],
        ])];
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function acceptsNestingAtTheLimitProvider(): iterable
    {
        yield 'an element chain exactly at the nesting limit' => [self::nestedElements(51)];
        yield 'a property payload exactly at the nesting limit' => [self::elementWithNestedValue(51)];
    }
}
