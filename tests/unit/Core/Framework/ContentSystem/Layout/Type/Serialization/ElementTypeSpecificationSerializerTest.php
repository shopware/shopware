<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\CopilotSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\ElementTypeSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\PropertySpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\SlotSpecificationDto;

/**
 * @internal
 */
#[CoversClass(ElementTypeSpecificationSerializer::class)]
class ElementTypeSpecificationSerializerTest extends TestCase
{
    private ElementTypeSpecificationSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new ElementTypeSpecificationSerializer();
    }

    #[TestDox('populates optional meta fields icon and category from input')]
    public function testPopulatesOptionalMetaScalarFieldsFromInput(): void
    {
        $data = [
            'meta' => [
                'label' => 'Product Card',
                'description' => 'A product card.',
                'vendor' => 'shopware AG',
                'icon' => 'card',
                'category' => 'commerce',
            ],
        ];

        $dto = $this->serializer->denormalize($data);

        static::assertSame('card', $dto->icon);
        static::assertSame('commerce', $dto->category);
    }

    #[TestDox('populates property fields from input')]
    public function testPopulatesPropertyFieldsFromInput(): void
    {
        $data = [
            'meta' => $this->buildMinimalMeta(),
            'properties' => [
                'product' => [
                    'type' => 'Shopware\Core\Content\Product\ProductEntity',
                    'required' => true,
                    'title' => 'Product',
                    'description' => 'The product.',
                ],
                'layout' => [
                    'type' => 'string',
                    'enum' => ['box', 'list'],
                    'default' => 'box',
                    'title' => 'Layout',
                    'description' => 'Layout variant.',
                ],
            ],
        ];

        $dto = $this->serializer->denormalize($data);

        static::assertCount(2, $dto->properties);
        static::assertSame('Shopware\Core\Content\Product\ProductEntity', $dto->properties['product']->type);
        static::assertTrue($dto->properties['product']->required);
        static::assertSame(['box', 'list'], $dto->properties['layout']->enum);
        static::assertSame('box', $dto->properties['layout']->default);
    }

    #[TestDox('populates slot fields from input')]
    public function testPopulatesSlotFieldsFromInput(): void
    {
        $data = [
            'meta' => $this->buildMinimalMeta(),
            'slots' => [
                ['name' => 'media', 'maxElements' => 1, 'allowList' => ['Sw:Media:Image'], 'description' => 'Media slot.'],
            ],
        ];

        $dto = $this->serializer->denormalize($data);

        static::assertCount(1, $dto->slots);
        static::assertSame('media', $dto->slots[0]->name);
        static::assertSame(1, $dto->slots[0]->maxElements);
        static::assertSame(['Sw:Media:Image'], $dto->slots[0]->allowList);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, list<string>}>
     */
    public static function denormalizesCopilotBlockProvider(): iterable
    {
        $baseMeta = ['label' => 'Text', 'description' => 'A text element.', 'vendor' => 'shopware AG'];

        yield 'explicit copilot block with summary and hints' => [
            array_merge($baseMeta, ['copilot' => ['summary' => 'Product card element.', 'hints' => ['Use for products.']]]),
            'Product card element.',
            ['Use for products.'],
        ];
        yield 'copilot block absent falls back to description' => [
            $baseMeta,
            'A text element.',
            [],
        ];
        yield 'copilot block with summary only defaults hints to empty' => [
            array_merge($baseMeta, ['copilot' => ['summary' => 'Custom summary.']]),
            'Custom summary.',
            [],
        ];
        yield 'copilot block with hints only falls back to description for summary' => [
            array_merge($baseMeta, ['copilot' => ['hints' => ['Hint one.']]]),
            'A text element.',
            ['Hint one.'],
        ];
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<string> $expectedHints
     */
    #[DataProvider('denormalizesCopilotBlockProvider')]
    #[TestDox('denormalizes copilot from meta block')]
    public function testDenormalizesCopilotBlock(array $meta, string $expectedSummary, array $expectedHints): void
    {
        $dto = $this->serializer->denormalize(['meta' => $meta]);

        static::assertSame($expectedSummary, $dto->copilot->summary);
        static::assertSame($expectedHints, $dto->copilot->hints);
    }

    #[TestDox('applies default values for absent optional property fields')]
    public function testAppliesPropertyDefaultsForAbsentFields(): void
    {
        $data = [
            'meta' => $this->buildMinimalMeta(),
            'properties' => [
                'text' => ['type' => 'string'],
            ],
        ];

        $dto = $this->serializer->denormalize($data);
        $prop = $dto->properties['text'];

        static::assertFalse($prop->required);
        static::assertFalse($prop->translatable);
        static::assertSame('', $prop->title);
        static::assertSame('', $prop->description);
        static::assertNull($prop->enum);
        static::assertNull($prop->default);
        static::assertNull($prop->adminUI);
    }

    #[TestDox('sets optional meta fields to null when absent')]
    public function testSetsOptionalMetaFieldsToNullWhenAbsent(): void
    {
        $data = [
            'meta' => $this->buildMinimalMeta(),
        ];

        $dto = $this->serializer->denormalize($data);

        static::assertNull($dto->icon);
        static::assertNull($dto->category);
        static::assertSame([], $dto->properties);
        static::assertSame([], $dto->slots);
    }

    #[TestDox('normalizes meta fields when populated')]
    public function testNormalizesMetaFieldsWhenPopulated(): void
    {
        $dto = new ElementTypeSpecificationDto(
            'Product Card',
            'A product card.',
            'shopware AG',
            'card',
            'commerce',
            new CopilotSpecificationDto('', []),
            [],
            [],
        );

        $normalized = $this->serializer->normalize($dto);

        static::assertSame('shopware AG', $normalized['meta']['vendor']);
        static::assertSame('card', $normalized['meta']['icon']);
        static::assertSame('commerce', $normalized['meta']['category']);
    }

    /**
     * @return iterable<string, array{CopilotSpecificationDto, array<string, mixed>}>
     */
    public static function normalizesCopilotBlockProvider(): iterable
    {
        yield 'both summary and hints' => [
            new CopilotSpecificationDto('Product card.', ['Use for products.']),
            ['summary' => 'Product card.', 'hints' => ['Use for products.']],
        ];
        yield 'summary only omits hints' => [
            new CopilotSpecificationDto('Summary only.', []),
            ['summary' => 'Summary only.'],
        ];
        yield 'hints only omits summary' => [
            new CopilotSpecificationDto('', ['Hint one.']),
            ['hints' => ['Hint one.']],
        ];
    }

    /**
     * @param array<string, mixed> $expectedCopilot
     */
    #[DataProvider('normalizesCopilotBlockProvider')]
    #[TestDox('normalizes copilot block')]
    public function testNormalizesCopilotBlock(CopilotSpecificationDto $copilot, array $expectedCopilot): void
    {
        $dto = new ElementTypeSpecificationDto('Text', 'Text.', 'shopware AG', null, null, $copilot, [], []);

        $normalized = $this->serializer->normalize($dto);

        static::assertSame($expectedCopilot, $normalized['meta']['copilot']);
    }

    #[TestDox('normalizes property fields when populated')]
    public function testNormalizesPropertyFieldsWhenPopulated(): void
    {
        $dto = $this->buildMinimalDto([
            'product' => new PropertySpecificationDto(
                'product',
                'Shopware\Core\Content\Product\ProductEntity',
                true,
                false,
                'Product',
                'The product.',
                null,
                null,
                null,
            ),
        ]);

        $normalized = $this->serializer->normalize($dto);

        static::assertSame('Shopware\Core\Content\Product\ProductEntity', $normalized['properties']['product']['type']);
        static::assertTrue($normalized['properties']['product']['required']);
    }

    #[TestDox('normalizes slot fields when populated')]
    public function testNormalizesSlotFieldsWhenPopulated(): void
    {
        $dto = $this->buildMinimalDto(slots: [new SlotSpecificationDto('media', 1, [], 'Media slot.')]);

        $normalized = $this->serializer->normalize($dto);

        static::assertSame('media', $normalized['slots'][0]['name']);
        static::assertSame(1, $normalized['slots'][0]['maxElements']);
        static::assertSame('Media slot.', $normalized['slots'][0]['description']);
    }

    #[TestDox('includes translatable flag in normalized property output')]
    public function testIncludesTranslatableFlagInNormalizedOutput(): void
    {
        $dto = $this->buildMinimalDto([
            'text' => new PropertySpecificationDto('text', 'string', false, true, 'Text', 'Content.', null, null, null),
        ]);

        $normalized = $this->serializer->normalize($dto);

        static::assertTrue($normalized['properties']['text']['translatable']);
    }

    #[TestDox('includes enum, default, and adminUI in normalized property output')]
    public function testNormalizesOptionalPropertyFields(): void
    {
        $dto = $this->buildMinimalDto([
            'layout' => new PropertySpecificationDto('layout', 'string', false, false, 'Layout', 'Variant.', ['box', 'list'], 'box', ['component' => 'select']),
        ]);

        $normalized = $this->serializer->normalize($dto);

        static::assertSame(['box', 'list'], $normalized['properties']['layout']['enum']);
        static::assertSame('box', $normalized['properties']['layout']['default']);
        static::assertSame(['component' => 'select'], $normalized['properties']['layout']['adminUI']);
    }

    #[TestDox('includes allowList in normalized slot output')]
    public function testNormalizesOptionalSlotFields(): void
    {
        $dto = $this->buildMinimalDto(slots: [new SlotSpecificationDto('media', null, ['Sw:Media:Image'], '')]);

        $normalized = $this->serializer->normalize($dto);

        static::assertSame(['Sw:Media:Image'], $normalized['slots'][0]['allowList']);
    }

    #[TestDox('omits optional fields from normalized output when values are defaults')]
    public function testOmitsDefaultValuesFromNormalizedOutput(): void
    {
        $dto = $this->buildMinimalDto();

        $normalized = $this->serializer->normalize($dto);

        static::assertArrayNotHasKey('icon', $normalized['meta']);
        static::assertArrayNotHasKey('category', $normalized['meta']);
        static::assertArrayNotHasKey('copilot', $normalized['meta']);
        static::assertArrayNotHasKey('properties', $normalized);
        static::assertArrayNotHasKey('slots', $normalized);
    }

    #[TestDox('omits empty title and description from normalized property output')]
    public function testOmitsEmptyTitleAndDescriptionFromNormalizedProperty(): void
    {
        $dto = $this->buildMinimalDto([
            'text' => new PropertySpecificationDto('text', 'string', false, false, '', '', null, null, null),
        ]);

        $normalized = $this->serializer->normalize($dto);

        static::assertArrayNotHasKey('title', $normalized['properties']['text']);
        static::assertArrayNotHasKey('description', $normalized['properties']['text']);
    }

    #[TestDox('omits empty description from normalized slot output')]
    public function testOmitsEmptyDescriptionFromNormalizedSlot(): void
    {
        $dto = $this->buildMinimalDto(slots: [new SlotSpecificationDto('media', 1, [], '')]);

        $normalized = $this->serializer->normalize($dto);

        static::assertArrayNotHasKey('description', $normalized['slots'][0]);
    }

    /**
     * @return array{label: string, description: string, vendor: string}
     */
    private function buildMinimalMeta(): array
    {
        return [
            'label' => 'Text',
            'description' => 'A text element.',
            'vendor' => 'shopware AG',
        ];
    }

    /**
     * @param array<string, PropertySpecificationDto> $properties
     * @param list<SlotSpecificationDto> $slots
     */
    private function buildMinimalDto(array $properties = [], array $slots = []): ElementTypeSpecificationDto
    {
        return new ElementTypeSpecificationDto(
            'Text',
            'Text.',
            'shopware AG',
            null,
            null,
            new CopilotSpecificationDto('', []),
            $properties,
            $slots,
        );
    }
}
