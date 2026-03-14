<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Specification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\SlotSpecification;

/**
 * @internal
 */
#[CoversClass(ContentElementTypeSpecification::class)]
class ContentElementTypeSpecificationTest extends TestCase
{
    #[TestDox('produces schema with all keys for a full specification')]
    public function testProducesSchemaForFullSpecification(): void
    {
        $def = $this->createFullSpecification();

        $schema = $def->toSchema();

        static::assertSame('Sw:Product:Card', $schema['name']);
        static::assertSame('Product Card', $schema['label']);
        static::assertSame('A product card element.', $schema['description']);
        static::assertSame('shopware AG', $schema['vendor']);
        static::assertSame('card', $schema['icon']);
        static::assertSame('commerce', $schema['category']);

        static::assertSame('Product card', $schema['copilot']['summary']);
        static::assertSame(['Use for single products'], $schema['copilot']['hints']);

        $product = $schema['properties']['product'];
        static::assertSame('Shopware\Core\Content\Product\ProductEntity', $product['type']);
        static::assertTrue($product['required']);
        static::assertFalse($product['translatable']);
        static::assertNull($product['enum']);
        static::assertNull($product['default']);
        static::assertNull($product['adminUI']);
        static::assertSame('Product', $product['title']);
        static::assertSame('The product to display.', $product['description']);

        $layout = $schema['properties']['layout'];
        static::assertSame('string', $layout['type']);
        static::assertFalse($layout['required']);
        static::assertSame(['box', 'list'], $layout['enum']);
        static::assertSame('box', $layout['default']);

        $media = $schema['slots'][0];
        static::assertSame('media', $media['name']);
        static::assertSame(1, $media['maxElements']);
        static::assertSame(['Sw:Media:Image'], $media['allowList']);
        static::assertSame('Media slot.', $media['description']);
    }

    #[TestDox('produces null icon and category when not provided')]
    public function testProducesNullIconAndCategoryWhenNotProvided(): void
    {
        $def = new ContentElementTypeSpecification(
            'Sw:Content:Text',
            'Text',
            'A text element.',
            'shopware AG',
            null,
            null,
            new CopilotSpecification('A text element.', []),
            [],
            [],
        );

        $schema = $def->toSchema();

        static::assertNull($schema['icon']);
        static::assertNull($schema['category']);
    }

    private function createFullSpecification(): ContentElementTypeSpecification
    {
        return new ContentElementTypeSpecification(
            'Sw:Product:Card',
            'Product Card',
            'A product card element.',
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
                    'The product to display.',
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
