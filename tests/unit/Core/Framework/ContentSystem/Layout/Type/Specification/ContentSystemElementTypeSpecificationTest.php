<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Specification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\SlotSpecification;

/**
 * @internal
 */
#[CoversClass(ContentSystemElementTypeSpecification::class)]
class ContentSystemElementTypeSpecificationTest extends TestCase
{
    #[TestDox('returns element type name')]
    public function testNameReturnsTypeName(): void
    {
        $spec = $this->createSpecification('card', 'commerce');

        static::assertSame('Sw:Product:Card', $spec->name());
    }

    #[TestDox('includes all top-level scalar fields in schema')]
    public function testToSchemaIncludesTopLevelScalarFields(): void
    {
        $spec = $this->createSpecification('card', 'commerce');
        $schema = $spec->toSchema();

        static::assertSame('Sw:Product:Card', $schema['name']);
        static::assertSame('Product Card', $schema['label']);
        static::assertSame('A product card.', $schema['description']);
        static::assertSame('shopware AG', $schema['vendor']);
        static::assertSame('card', $schema['icon']);
        static::assertSame('commerce', $schema['category']);
    }

    #[TestDox('preserves property keys and delegates to property specifications')]
    public function testToSchemaPreservesPropertyKeys(): void
    {
        $spec = $this->createFullSpecification();
        $schema = $spec->toSchema();

        static::assertCount(2, $schema['properties']);
        static::assertArrayHasKey('product', $schema['properties']);
        static::assertArrayHasKey('layout', $schema['properties']);
    }

    #[TestDox('maps slots as indexed array')]
    public function testToSchemaMapsSlots(): void
    {
        $spec = $this->createFullSpecification();
        $schema = $spec->toSchema();

        static::assertCount(1, $schema['slots']);
        static::assertIsArray($schema['slots'][0]);
    }

    #[TestDox('includes null for absent optional fields and empty collections')]
    public function testToSchemaHandlesAbsentOptionalFields(): void
    {
        $spec = $this->createSpecification(null, null);
        $schema = $spec->toSchema();

        static::assertNull($schema['icon']);
        static::assertNull($schema['category']);
        static::assertIsArray($schema['copilot']);
        static::assertSame([], $schema['properties']);
        static::assertSame([], $schema['slots']);
    }

    private function createSpecification(?string $icon, ?string $category): ContentSystemElementTypeSpecification
    {
        return new ContentSystemElementTypeSpecification(
            'Sw:Product:Card',
            'Product Card',
            'A product card.',
            'shopware AG',
            $icon,
            $category,
            new CopilotSpecification('Product card', ['Use for single products']),
            [],
            [],
        );
    }

    private function createFullSpecification(): ContentSystemElementTypeSpecification
    {
        return new ContentSystemElementTypeSpecification(
            'Sw:Product:Card',
            'Product Card',
            'A product card.',
            'shopware AG',
            'card',
            'commerce',
            new CopilotSpecification('Product card', ['Use for single products']),
            [
                'product' => new PropertySpecification(
                    'product',
                    new PropertyType('Shopware\Core\Content\Product\ProductEntity', false, null, null),
                    true,
                    'Product',
                    'The product.',
                    null,
                ),
                'layout' => new PropertySpecification(
                    'layout',
                    new PropertyType('string', false, ['box', 'list'], 'box'),
                    false,
                    'Layout',
                    'Layout variant.',
                    null,
                ),
            ],
            [
                new SlotSpecification('media', 1, ['Sw:Media:Image'], 'Media slot.'),
            ],
        );
    }
}
