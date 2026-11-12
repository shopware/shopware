<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Serialization\StyleOptionSpecificationSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
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

    #[TestDox('carries every wrong-typed optional facet through raw for the validator to reject')]
    public function testDenormalizeCarriesRawWrongTypedFacets(): void
    {
        $dto = $this->serializer->denormalize([
            'type' => 'string',
            'maxLength' => '64',
            'enum' => 'not-an-array',
            'range' => 'not-an-array',
            'adminUI' => 'not-an-array',
            'default' => ['not', 'a', 'scalar'],
        ]);

        static::assertSame('64', $dto->maxLength);
        static::assertSame('not-an-array', $dto->enum);
        static::assertSame('not-an-array', $dto->range);
        static::assertSame('not-an-array', $dto->adminUI);
        static::assertSame(['not', 'a', 'scalar'], $dto->default);
    }

    #[TestDox('normalize omits absent optional facets, keeping only the declared type')]
    public function testNormalizeOmitsAbsentFacets(): void
    {
        $dto = $this->serializer->denormalize(['type' => 'boolean', 'default' => true]);

        static::assertSame(['type' => 'boolean', 'default' => true], $this->serializer->normalize($dto));
    }

    #[TestDox('preserves the declaration through a denormalize-then-normalize round-trip')]
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

    /**
     * @return iterable<string, array{array<string, mixed>, bool|null}>
     */
    public static function breakpointAwareDenormalizeProvider(): iterable
    {
        yield 'absent breakpointAware yields null' => [['type' => 'boolean'], null];
        yield 'false breakpointAware yields false' => [['type' => 'boolean', 'breakpointAware' => false], false];
        yield 'true breakpointAware yields true' => [['type' => 'boolean', 'breakpointAware' => true], true];
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('breakpointAwareDenormalizeProvider')]
    #[TestDox('denormalize maps breakpointAware from the source array')]
    public function testDenormalizeBreakpointAware(array $input, ?bool $expected): void
    {
        $dto = $this->serializer->denormalize($input);

        static::assertSame($expected, $dto->breakpointAware);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function breakpointAwareNormalizeProvider(): iterable
    {
        yield 'emits false' => [['type' => 'boolean', 'breakpointAware' => false], false];
        yield 'emits true' => [['type' => 'boolean', 'breakpointAware' => true], true];
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('breakpointAwareNormalizeProvider')]
    #[TestDox('normalize emits the breakpointAware flag when it is set')]
    public function testNormalizeBreakpointAware(array $input, bool $expected): void
    {
        $normalized = $this->serializer->normalize($this->serializer->denormalize($input));

        static::assertSame($expected, $normalized['breakpointAware']);
    }

    #[TestDox('normalize omits the breakpointAware key when it is null')]
    public function testNormalizeOmitsBreakpointAwareWhenNull(): void
    {
        $normalized = $this->serializer->normalize($this->serializer->denormalize(['type' => 'boolean']));

        static::assertArrayNotHasKey('breakpointAware', $normalized);
    }

    #[TestDox('denormalize maps a declared kind from the source array')]
    public function testDenormalizeMapsKind(): void
    {
        $dto = $this->serializer->denormalize(['type' => 'string', 'kind' => 'box-spacing']);

        static::assertSame('box-spacing', $dto->kind);
    }

    #[TestDox('denormalize yields a null kind when the key is absent')]
    public function testDenormalizeKindAbsentYieldsNull(): void
    {
        static::assertNull($this->serializer->denormalize(['type' => 'string'])->kind);
    }

    #[TestDox('round-trips a declared kind through denormalize then normalize')]
    public function testRoundTripPreservesKind(): void
    {
        $raw = ['type' => 'string', 'maxLength' => 64, 'kind' => 'box-spacing'];

        static::assertSame($raw, $this->serializer->normalize($this->serializer->denormalize($raw)));
    }

    #[TestDox('normalize omits the kind key when it is null')]
    public function testNormalizeOmitsKindWhenNull(): void
    {
        $normalized = $this->serializer->normalize($this->serializer->denormalize(['type' => 'string']));

        static::assertArrayNotHasKey('kind', $normalized);
    }
}
