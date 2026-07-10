<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('denormalizeProvider')]
    #[TestDox('denormalize maps facets: $_dataName')]
    public function testDenormalize(array $input, mixed $expectedType, mixed $expectedLabel, mixed $expectedResolves, mixed $expectedInputs): void
    {
        $dto = $this->serializer->denormalize($input);

        static::assertSame($expectedType, $dto->type);
        static::assertSame($expectedLabel, $dto->label);
        static::assertSame($expectedResolves, $dto->resolves);
        static::assertSame($expectedInputs, $dto->inputs);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, mixed, mixed, mixed, mixed}>
     */
    public static function denormalizeProvider(): iterable
    {
        yield 'maps every declared facet from the raw map' => [
            [
                'type' => 'media-gallery',
                'label' => 'From media library',
                'resolves' => ['image' => ['loader' => 'entity', 'config' => ['entity' => 'media']]],
                'inputs' => ['alt' => ['default' => 'fallback alt']],
            ],
            'media-gallery',
            'From media library',
            ['image' => ['loader' => 'entity', 'config' => ['entity' => 'media']]],
            ['alt' => ['default' => 'fallback alt']],
        ];

        yield 'falls back to null facets for an empty map' => [[], null, null, null, null];

        yield 'carries every wrong-typed facet raw for the validator to reject' => [
            ['type' => 42, 'label' => false, 'resolves' => 'not-an-array', 'inputs' => 'not-an-array'],
            42,
            false,
            'not-an-array',
            'not-an-array',
        ];
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

    #[TestDox('normalize keeps empty resolves/inputs as [], not null')]
    public function testNormalizeKeepsEmptyResolvesInputsAsArraysWhenAbsent(): void
    {
        $dto = $this->serializer->denormalize(['type' => 'media-gallery', 'label' => 'x', 'resolves' => [], 'inputs' => []]);

        static::assertSame([
            'type' => 'media-gallery',
            'label' => 'x',
            'resolves' => [],
            'inputs' => [],
        ], $this->serializer->normalize($dto));
    }
}
