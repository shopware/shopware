<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Specification\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\PropertySpecificationDto;

/**
 * @internal
 */
#[CoversClass(PropertySpecificationDto::class)]
class PropertySpecificationDtoTest extends TestCase
{
    #[TestDox('groups flat fields into PropertyType and forwards to PropertySpecification')]
    public function testToPropertySpecificationGroupsFieldsIntoPropertyType(): void
    {
        $dto = new PropertySpecificationDto(
            'product',
            'Shopware\Core\Content\Product\ProductEntity',
            true,
            false,
            'Product',
            'The product.',
            null,
            null,
            null,
        );

        $schema = $dto->toPropertySpecification()->toSchema();

        static::assertSame('Shopware\Core\Content\Product\ProductEntity', $schema['type']);
        static::assertFalse($schema['translatable']);
        static::assertTrue($schema['required']);
        static::assertSame('Product', $schema['title']);
        static::assertSame('The product.', $schema['description']);
        static::assertNull($schema['enum']);
        static::assertNull($schema['default']);
        static::assertNull($schema['adminUI']);
    }

    #[TestDox('groups enum and default into PropertyType')]
    public function testToPropertySpecificationGroupsEnumAndDefault(): void
    {
        $dto = new PropertySpecificationDto(
            'layout',
            'string',
            false,
            true,
            'Layout',
            'Layout variant.',
            ['box', 'list'],
            'box',
            ['field' => 'mt:text:field'],
        );

        $schema = $dto->toPropertySpecification()->toSchema();

        static::assertSame('string', $schema['type']);
        static::assertTrue($schema['translatable']);
        static::assertSame(['box', 'list'], $schema['enum']);
        static::assertSame('box', $schema['default']);
        static::assertSame(['field' => 'mt:text:field'], $schema['adminUI']);
    }
}
