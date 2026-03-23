<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
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

    #[TestDox('populates all meta scalar fields from input')]
    public function testMapsAllMetaScalarFieldsFromInput(): void
    {
        $data = [
            'meta' => [
                'name' => 'Sw:Product:Card',
                'label' => 'Product Card',
                'description' => 'A product card.',
                'vendor' => 'shopware AG',
                'icon' => 'card',
                'category' => 'commerce',
            ],
        ];

        $dto = $this->serializer->denormalize($data);

        static::assertSame('Sw:Product:Card', $dto->name);
        static::assertSame('Product Card', $dto->label);
        static::assertSame('A product card.', $dto->description);
        static::assertSame('shopware AG', $dto->vendor);
        static::assertSame('card', $dto->icon);
        static::assertSame('commerce', $dto->category);
    }

    #[TestDox('populates copilot from explicit copilot block')]
    public function testMapsCopilotFromExplicitBlock(): void
    {
        $data = [
            'meta' => [
                'name' => 'Sw:Product:Card',
                'label' => 'Product Card',
                'description' => 'A product card.',
                'vendor' => 'shopware AG',
                'copilot' => [
                    'summary' => 'Product card element.',
                    'hints' => ['Use for products.'],
                ],
            ],
        ];

        $dto = $this->serializer->denormalize($data);

        static::assertSame('Product card element.', $dto->copilot->summary);
        static::assertSame(['Use for products.'], $dto->copilot->hints);
    }

    #[TestDox('maps property fields from input')]
    public function testMapsPropertyFieldsFromInput(): void
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

    #[TestDox('maps slot fields from input')]
    public function testMapsSlotFieldsFromInput(): void
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

    #[TestDox('preserves all fields through normalize round trip')]
    public function testPreservesAllFieldsThroughRoundTrip(): void
    {
        $dto = new ElementTypeSpecificationDto(
            'Sw:Product:Card',
            'Product Card',
            'A product card.',
            'shopware AG',
            'card',
            'commerce',
            new CopilotSpecificationDto('Product card.', ['Use for products.']),
            [
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
            ],
            [
                new SlotSpecificationDto('media', 1, [], 'Media slot.'),
            ],
        );

        $normalized = $this->serializer->normalize($dto);

        static::assertSame('Sw:Product:Card', $normalized['meta']['name']);
        static::assertSame('shopware AG', $normalized['meta']['vendor']);
        static::assertSame('card', $normalized['meta']['icon']);
        static::assertSame('Product card.', $normalized['meta']['copilot']['summary']);
        static::assertSame('Shopware\Core\Content\Product\ProductEntity', $normalized['properties']['product']['type']);
        static::assertTrue($normalized['properties']['product']['required']);
        static::assertSame('media', $normalized['slots'][0]['name']);
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

    #[TestDox('applies property defaults when optional property fields are absent')]
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

        static::assertSame('text', $prop->name);
        static::assertSame('string', $prop->type);
        static::assertFalse($prop->required);
        static::assertFalse($prop->translatable);
        static::assertSame('', $prop->title);
        static::assertSame('', $prop->description);
        static::assertNull($prop->enum);
        static::assertNull($prop->default);
        static::assertNull($prop->adminUI);
    }

    #[TestDox('sets optional fields to defaults and uses description as copilot summary when not provided')]
    public function testFallsBackToDefaultsWhenOptionalFieldsAbsent(): void
    {
        $data = [
            'meta' => $this->buildMinimalMeta(),
        ];

        $dto = $this->serializer->denormalize($data);

        static::assertNull($dto->icon);
        static::assertNull($dto->category);
        static::assertSame('A text element.', $dto->copilot->summary);
        static::assertSame([], $dto->copilot->hints);
        static::assertSame([], $dto->properties);
        static::assertSame([], $dto->slots);
    }

    #[TestDox('includes enum, default, adminUI in normalized property and allowList in normalized slot')]
    public function testNormalizesOptionalPropertyAndSlotFields(): void
    {
        $dto = new ElementTypeSpecificationDto(
            'Sw:Content:Text',
            'Text',
            'Text.',
            'shopware AG',
            null,
            null,
            new CopilotSpecificationDto('', []),
            [
                'layout' => new PropertySpecificationDto('layout', 'string', false, false, 'Layout', 'Variant.', ['box', 'list'], 'box', ['component' => 'select']),
            ],
            [
                new SlotSpecificationDto('media', null, ['Sw:Media:Image'], ''),
            ],
        );

        $normalized = $this->serializer->normalize($dto);

        static::assertSame(['box', 'list'], $normalized['properties']['layout']['enum']);
        static::assertSame('box', $normalized['properties']['layout']['default']);
        static::assertSame(['component' => 'select'], $normalized['properties']['layout']['adminUI']);
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

    /**
     * @return array{name: string, label: string, description: string, vendor: string}
     */
    private function buildMinimalMeta(): array
    {
        return [
            'name' => 'Sw:Content:Text',
            'label' => 'Text',
            'description' => 'A text element.',
            'vendor' => 'shopware AG',
        ];
    }

    /**
     * @param array<string, PropertySpecificationDto> $properties
     */
    private function buildMinimalDto(array $properties = []): ElementTypeSpecificationDto
    {
        return new ElementTypeSpecificationDto(
            'Sw:Content:Text',
            'Text',
            'Text.',
            'shopware AG',
            null,
            null,
            new CopilotSpecificationDto('', []),
            $properties,
            [],
        );
    }
}
