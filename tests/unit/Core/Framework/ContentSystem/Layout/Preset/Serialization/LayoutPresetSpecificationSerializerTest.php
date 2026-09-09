<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Preset\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
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

    private function serializer(): LayoutPresetSpecificationSerializer
    {
        return new LayoutPresetSpecificationSerializer();
    }
}
