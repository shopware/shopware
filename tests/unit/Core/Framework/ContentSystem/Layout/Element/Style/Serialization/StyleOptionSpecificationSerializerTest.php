<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Serialization\StyleOptionSpecificationSerializer;

/**
 * @internal
 */
#[CoversClass(StyleOptionSpecificationSerializer::class)]
class StyleOptionSpecificationSerializerTest extends TestCase
{
    private StyleOptionSpecificationSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new StyleOptionSpecificationSerializer();
    }

    #[TestDox('denormalize maps every declared facet from the raw map')]
    public function testDenormalizeMapsAllFacets(): void
    {
        $dto = $this->serializer->denormalize([
            'type' => 'integer',
            'enum' => [1, 2, 3],
            'range' => ['min' => 1, 'max' => 12],
            'maxLength' => null,
            'default' => 2,
            'adminUI' => ['component' => 'number'],
        ]);

        static::assertSame('integer', $dto->type);
        static::assertSame([1, 2, 3], $dto->enum);
        static::assertSame(['min' => 1, 'max' => 12], $dto->range);
        static::assertSame(2, $dto->default);
        static::assertSame(['component' => 'number'], $dto->adminUI);
    }

    #[TestDox('denormalize falls back to empty type and null facets for an empty map')]
    public function testDenormalizeFallsBackForEmptyMap(): void
    {
        $dto = $this->serializer->denormalize([]);

        static::assertSame('', $dto->type);
        static::assertNull($dto->enum);
        static::assertNull($dto->range);
        static::assertNull($dto->maxLength);
        static::assertNull($dto->default);
        static::assertNull($dto->adminUI);
    }

    #[TestDox('denormalize ignores a non-integer maxLength rather than failing the type contract')]
    public function testDenormalizeIgnoresNonIntegerMaxLength(): void
    {
        $dto = $this->serializer->denormalize(['type' => 'string', 'maxLength' => '64']);

        static::assertNull($dto->maxLength);
    }

    #[TestDox('normalize omits absent optional facets, keeping only the declared type')]
    public function testNormalizeOmitsAbsentFacets(): void
    {
        $dto = $this->serializer->denormalize(['type' => 'boolean', 'default' => true]);

        static::assertSame(['type' => 'boolean', 'default' => true], $this->serializer->normalize($dto));
    }

    #[TestDox('a denormalize then normalize round-trip preserves the declaration')]
    public function testRoundTripPreservesDeclaration(): void
    {
        $raw = [
            'type' => 'string',
            'enum' => ['start', 'end'],
            'maxLength' => 64,
            'default' => 'start',
            'adminUI' => ['component' => 'select', 'label' => 'Align'],
        ];

        static::assertSame($raw, $this->serializer->normalize($this->serializer->denormalize($raw)));
    }
}
