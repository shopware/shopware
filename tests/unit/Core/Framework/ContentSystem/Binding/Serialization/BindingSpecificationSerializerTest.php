<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
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
     * @param array<string, mixed> $input
     * @param array<string, mixed> $expected
     */
    #[DataProvider('normalizeProvider')]
    #[TestDox('normalize maps facets: $_dataName')]
    public function testNormalize(array $input, array $expected): void
    {
        $dto = $this->serializer->denormalize($input);

        $normalized = $this->serializer->normalize($dto);

        static::assertSame($expected, $normalized);
        static::assertArrayNotHasKey('id', $normalized);
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

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function normalizeProvider(): iterable
    {
        yield 'emits every facet except id' => [
            [
                'type' => 'media-gallery',
                'label' => 'From media library',
                'resolves' => ['image' => ['loader' => 'entity', 'config' => []]],
                'inputs' => ['alt' => []],
            ],
            [
                'type' => 'media-gallery',
                'label' => 'From media library',
                'resolves' => ['image' => ['loader' => 'entity', 'config' => []]],
                'inputs' => ['alt' => []],
            ],
        ];

        yield 'keeps empty resolves and inputs as arrays when absent' => [
            ['type' => 'media-gallery', 'label' => 'x', 'resolves' => [], 'inputs' => []],
            [
                'type' => 'media-gallery',
                'label' => 'x',
                'resolves' => [],
                'inputs' => [],
            ],
        ];
    }
}
