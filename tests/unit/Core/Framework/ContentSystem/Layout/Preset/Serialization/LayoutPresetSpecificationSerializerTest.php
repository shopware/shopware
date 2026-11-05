<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Preset\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Serialization\LayoutPresetSpecificationSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LayoutPresetSpecificationSerializer::class)]
class LayoutPresetSpecificationSerializerTest extends TestCase
{
    #[TestDox('denormalizes the raw authoring form into a dto without compiling')]
    public function testDenormalizeMapsRawToDto(): void
    {
        $dto = $this->serializer()->denormalize([
            'name' => 'Text block',
            'description' => 'A text block.',
            'icon' => 'regular-align-left',
            'layout' => [['component' => 'Sw:Content:Text']],
        ]);

        static::assertSame('Text block', $dto->name);
        static::assertSame('A text block.', $dto->description);
        static::assertSame('regular-align-left', $dto->icon);
        static::assertSame([['component' => 'Sw:Content:Text']], $dto->layout);
    }

    #[TestDox('coerces absent or non-string metadata to empty strings so validation rejects it')]
    public function testDenormalizeCoercesMissingMetadataToEmpty(): void
    {
        $dto = $this->serializer()->denormalize(['name' => 'Empty', 'layout' => []]);

        static::assertSame('', $dto->description);
        static::assertSame('', $dto->icon);
        static::assertNull($dto->category);
        static::assertSame(0, $dto->priority);
    }

    #[TestDox('normalizes a dto back into the raw authoring form')]
    public function testNormalizeIsInverseOfDenormalize(): void
    {
        $serializer = $this->serializer();

        $raw = [
            'name' => 'Text block',
            'description' => 'A text block.',
            'icon' => 'regular-align-left',
            'layout' => [['component' => 'Sw:Content:Text']],
        ];

        static::assertSame($raw, $serializer->normalize($serializer->denormalize($raw)));
    }

    #[DataProvider('metadataProvider')]
    public function testRoundTripsCategoryAndPriorityThroughAppJson(?string $category, int $priority): void
    {
        $serializer = $this->serializer();
        $dto = $serializer->denormalize([
            'name' => 'Text block',
            'description' => 'A text block.',
            'icon' => 'regular-align-left',
            'layout' => [],
            'category' => $category,
            'priority' => $priority,
        ]);
        $persisted = json_encode($serializer->normalize($dto), \JSON_THROW_ON_ERROR);
        $restored = $serializer->denormalize(json_decode($persisted, true, 512, \JSON_THROW_ON_ERROR));

        static::assertSame($category, $restored->category);
        static::assertSame($priority, $restored->priority);
    }

    /**
     * @return iterable<string, array{string|null, int}>
     */
    public static function metadataProvider(): iterable
    {
        yield 'content preset' => ['content', 200];
        yield 'lower priority in a custom category' => ['my-plugin', -100];
        yield 'default metadata' => [null, 0];
    }

    #[DataProvider('invalidMetadataProvider')]
    public function testRejectsMalformedMetadata(string $field, mixed $value): void
    {
        $this->expectException(\TypeError::class);

        $dto = $this->serializer()->denormalize([$field => $value]);

        static::fail('Unexpectedly accepted invalid ' . $field . ' in preset ' . $dto->name . '.');
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function invalidMetadataProvider(): iterable
    {
        yield 'numeric priority string' => ['priority', '100'];
        yield 'whole number priority float' => ['priority', 100.0];
        yield 'fractional priority' => ['priority', 1.5];
        yield 'boolean priority' => ['priority', true];
        yield 'explicit null priority' => ['priority', null];
        yield 'priority array' => ['priority', []];
        yield 'numeric category' => ['category', 100];
        yield 'boolean category' => ['category', false];
        yield 'category array' => ['category', []];
    }

    private function serializer(): LayoutPresetSpecificationSerializer
    {
        return new LayoutPresetSpecificationSerializer();
    }
}
