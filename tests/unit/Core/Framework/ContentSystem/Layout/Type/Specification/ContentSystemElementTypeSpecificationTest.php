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
    #[TestDox('includes all top-level scalar fields in schema')]
    public function testToSchemaIncludesTopLevelScalarFields(): void
    {
        $spec = $this->createSpecification('card', 'commerce');
        $schema = $spec->toSchema();

        static::assertSame('Sw:Product:Card', $schema['name']);
        static::assertSame('Product Card', $schema['label']);
        static::assertSame('A product card.', $schema['description']);
        static::assertSame('test', $schema['source']);
        static::assertSame('card', $schema['icon']);
        static::assertSame('commerce', $schema['category']);
    }

    #[TestDox('includes property keys and slots in schema')]
    public function testToSchemaIncludesPropertyKeysAndSlots(): void
    {
        $spec = $this->createFullSpecification();
        $schema = $spec->toSchema();

        static::assertCount(2, $schema['properties']);
        static::assertArrayHasKey('product', $schema['properties']);
        static::assertArrayHasKey('layout', $schema['properties']);
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

    #[TestDox('includes source field in schema output')]
    public function testToSchemaIncludesSource(): void
    {
        $specification = new ContentSystemElementTypeSpecification(
            'Sw:Content:Text',
            'Text',
            '',
            null,
            null,
            new CopilotSpecification('', []),
            [],
            [],
            'core'
        );

        static::assertSame('core', $specification->toSchema()['source']);
    }

    private function createSpecification(?string $icon, ?string $category): ContentSystemElementTypeSpecification
    {
        return new ContentSystemElementTypeSpecification(
            'Sw:Product:Card',
            'Product Card',
            'A product card.',
            $icon,
            $category,
            new CopilotSpecification('Product card', ['Use for single products']),
            [],
            [],
            'test',
        );
    }

    private function createFullSpecification(): ContentSystemElementTypeSpecification
    {
        return new ContentSystemElementTypeSpecification(
            'Sw:Product:Card',
            'Product Card',
            'A product card.',
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
            'test',
        );
    }
}
