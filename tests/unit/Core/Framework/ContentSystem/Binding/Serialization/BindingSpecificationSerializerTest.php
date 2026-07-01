<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;

/**
 * @internal
 */
#[CoversClass(BindingSpecificationSerializer::class)]
class BindingSpecificationSerializerTest extends TestCase
{
    private BindingSpecificationSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new BindingSpecificationSerializer();
    }

    #[TestDox('denormalize maps every declared facet from the raw map')]
    public function testDenormalizeMapsAllFacets(): void
    {
        $dto = $this->serializer->denormalize([
            'type' => 'media-gallery',
            'label' => 'From media library',
            'resolves' => ['image' => ['loader' => 'entity', 'config' => ['entity' => 'media']]],
            'inputs' => ['alt' => ['default' => 'fallback alt']],
        ]);

        static::assertSame('media-gallery', $dto->type);
        static::assertSame('From media library', $dto->label);
        static::assertSame(['image' => ['loader' => 'entity', 'config' => ['entity' => 'media']]], $dto->resolves);
        static::assertSame(['alt' => ['default' => 'fallback alt']], $dto->inputs);
    }

    #[TestDox('denormalize falls back to null facets for an empty map')]
    public function testDenormalizeFallsBackForEmptyMap(): void
    {
        $dto = $this->serializer->denormalize([]);

        static::assertNull($dto->type);
        static::assertNull($dto->label);
        static::assertNull($dto->resolves);
        static::assertNull($dto->inputs);
    }

    #[TestDox('carries every wrong-typed facet through raw for the validator to reject')]
    public function testDenormalizeCarriesRawWrongTypedFacets(): void
    {
        $dto = $this->serializer->denormalize([
            'type' => 42,
            'label' => false,
            'resolves' => 'not-an-array',
            'inputs' => 'not-an-array',
        ]);

        static::assertSame(42, $dto->type);
        static::assertFalse($dto->label);
        static::assertSame('not-an-array', $dto->resolves);
        static::assertSame('not-an-array', $dto->inputs);
    }

    #[TestDox('does not denormalize id, which the loader supplies separately from the YAML body')]
    public function testDenormalizeIgnoresId(): void
    {
        $dto = $this->serializer->denormalize(['id' => 'from-media-library', 'type' => 'media-gallery']);

        static::assertSame('media-gallery', $dto->type);
    }

    #[TestDox('normalize emits type, label, resolves and inputs but never id')]
    public function testNormalizeEmitsAllFacetsExceptId(): void
    {
        $dto = $this->serializer->denormalize([
            'type' => 'media-gallery',
            'label' => 'From media library',
            'resolves' => ['image' => ['loader' => 'entity', 'config' => []]],
            'inputs' => ['alt' => []],
        ]);

        $normalized = $this->serializer->normalize($dto);

        static::assertSame([
            'type' => 'media-gallery',
            'label' => 'From media library',
            'resolves' => ['image' => ['loader' => 'entity', 'config' => []]],
            'inputs' => ['alt' => []],
        ], $normalized);
        static::assertArrayNotHasKey('id', $normalized);
    }

    #[TestDox('normalize keeps an empty resolves/inputs map as [], not null')]
    public function testNormalizeKeepsEmptyResolvesAndInputsAsEmptyArray(): void
    {
        $dto = $this->serializer->denormalize(['type' => 'media-gallery', 'label' => 'x', 'resolves' => [], 'inputs' => []]);

        $normalized = $this->serializer->normalize($dto);

        static::assertSame([], $normalized['resolves']);
        static::assertSame([], $normalized['inputs']);
    }

    #[TestDox('preserves the declaration through a denormalize-then-normalize round-trip')]
    public function testRoundTripPreservesDeclarationMinusId(): void
    {
        $raw = [
            'id' => 'from-media-library',
            'type' => 'media-gallery',
            'label' => 'From media library',
            'resolves' => ['image' => ['loader' => 'entity', 'config' => ['entity' => 'media']]],
            'inputs' => ['alt' => ['default' => 'fallback alt']],
        ];

        $normalized = $this->serializer->normalize($this->serializer->denormalize($raw));

        static::assertSame([
            'type' => 'media-gallery',
            'label' => 'From media library',
            'resolves' => ['image' => ['loader' => 'entity', 'config' => ['entity' => 'media']]],
            'inputs' => ['alt' => ['default' => 'fallback alt']],
        ], $normalized);
    }
}
