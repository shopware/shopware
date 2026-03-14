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

    #[TestDox('populates all meta fields from input')]
    public function testDenormalizePopulatesMetaFields(): void
    {
        $data = [
            'meta' => [
                'name' => 'Sw:Product:Card',
                'label' => 'Product Card',
                'description' => 'A product card.',
                'vendor' => 'shopware AG',
                'icon' => 'card',
                'category' => 'commerce',
                'copilot' => [
                    'summary' => 'Product card element.',
                    'hints' => ['Use for products.'],
                ],
            ],
        ];

        $dto = $this->serializer->denormalize($data);

        static::assertSame('Sw:Product:Card', $dto->name);
        static::assertSame('Product Card', $dto->label);
        static::assertSame('A product card.', $dto->description);
        static::assertSame('shopware AG', $dto->vendor);
        static::assertSame('card', $dto->icon);
        static::assertSame('commerce', $dto->category);
        static::assertSame('Product card element.', $dto->copilot->summary);
        static::assertSame(['Use for products.'], $dto->copilot->hints);
    }

    #[TestDox('maps property fields from input')]
    public function testDenormalizePopulatesProperties(): void
    {
        $data = [
            'meta' => [
                'name' => 'Sw:Product:Card',
                'label' => 'Product Card',
                'description' => 'A product card.',
                'vendor' => 'shopware AG',
            ],
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
    public function testDenormalizePopulatesSlots(): void
    {
        $data = [
            'meta' => [
                'name' => 'Sw:Product:Card',
                'label' => 'Product Card',
                'description' => 'A product card.',
                'vendor' => 'shopware AG',
            ],
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

    #[TestDox('applies defaults and uses description as copilot summary when optional fields are absent')]
    public function testDenormalizeAppliesDefaults(): void
    {
        $data = [
            'meta' => [
                'name' => 'Sw:Content:Text',
                'label' => 'Text',
                'description' => 'A text element.',
                'vendor' => 'shopware AG',
            ],
        ];

        $dto = $this->serializer->denormalize($data);

        static::assertSame('Sw:Content:Text', $dto->name);
        static::assertNull($dto->icon);
        static::assertNull($dto->category);
        static::assertSame('A text element.', $dto->copilot->summary);
        static::assertSame([], $dto->copilot->hints);
        static::assertSame([], $dto->properties);
        static::assertSame([], $dto->slots);
    }

    #[TestDox('applies property defaults when optional property fields are absent')]
    public function testDenormalizeAppliesPropertyDefaults(): void
    {
        $data = [
            'meta' => [
                'name' => 'Sw:Content:Text',
                'label' => 'Text',
                'description' => 'Text.',
                'vendor' => 'shopware AG',
            ],
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

    #[TestDox('preserves all fields through normalize round trip')]
    public function testNormalizeRoundTrip(): void
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

    #[TestDox('omits optional fields from normalized output when values are defaults')]
    public function testNormalizeOmitsDefaults(): void
    {
        $dto = new ElementTypeSpecificationDto(
            'Sw:Content:Text',
            'Text',
            'Text.',
            'shopware AG',
            null,
            null,
            new CopilotSpecificationDto('Text.', []),
            [],
            [],
        );

        $normalized = $this->serializer->normalize($dto);

        static::assertArrayNotHasKey('icon', $normalized['meta']);
        static::assertArrayNotHasKey('category', $normalized['meta']);
        static::assertArrayNotHasKey('properties', $normalized);
        static::assertArrayNotHasKey('slots', $normalized);
    }
}
