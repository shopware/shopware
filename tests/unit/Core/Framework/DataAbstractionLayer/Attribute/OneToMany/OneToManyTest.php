<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Attribute\AttributeTestFixtures;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OneToMany::class)]
final class OneToManyTest extends TestCase
{
    public function testCreateField(): void
    {
        $attribute = new OneToMany(
            entity: 'order_line_item',
            ref: 'order_id'
        );

        $field = $attribute->createField(
            'lineItems',
            'line_items',
            'order'
        );

        static::assertInstanceOf(OneToManyAssociationField::class, $field);
        static::assertSame('lineItems', $field->getPropertyName());
        static::assertSame('order_line_item', $attribute->entity);
        static::assertSame('order_id', $attribute->ref);
    }

    public function testLocalFieldId(): void
    {
        $attribute = new OneToMany(
            entity: AttributeTestFixtures::ENTITY_NAME_PRODUCT,
            ref: 'category_id'
        );

        $field = $attribute->createField(
            'products',
            'products',
            AttributeTestFixtures::ENTITY_NAME_CATEGORY
        );

        static::assertInstanceOf(OneToManyAssociationField::class, $field);
        static::assertSame('products', $field->getPropertyName());
    }

    public function testGetFieldClass(): void
    {
        $attribute = new OneToMany(
            entity: AttributeTestFixtures::ENTITY_NAME_PRODUCT,
            ref: 'category_id'
        );

        static::assertSame(OneToManyAssociationField::class, $attribute->getFieldClass());
    }

    public function testFromArray(): void
    {
        $data = [
            'entity' => 'order_line_item',
            'ref' => 'order_id',
            'onDelete' => OnDelete::CASCADE->value,
            'api' => true,
            'nullable' => false,
            'type' => OneToMany::TYPE,
            'translated' => false,
        ];

        $attribute = OneToMany::fromArray($data);

        static::assertSame('order_line_item', $attribute->entity);
        static::assertSame('order_id', $attribute->ref);
        static::assertSame(OnDelete::CASCADE, $attribute->onDelete);
        static::assertTrue($attribute->api);
        static::assertFalse($attribute->nullable);
    }

    public function testOnDeleteSetNull(): void
    {
        $attribute = new OneToMany(
            entity: AttributeTestFixtures::ENTITY_NAME_PRODUCT,
            ref: 'manufacturer_id',
            onDelete: OnDelete::SET_NULL
        );

        $field = $attribute->createField(
            'products',
            'products',
            'manufacturer'
        );

        static::assertInstanceOf(OneToManyAssociationField::class, $field);
        static::assertSame('products', $field->getPropertyName());
    }

    public function testToDefinition(): void
    {
        $attribute = new OneToMany(
            entity: 'order_line_item',
            ref: 'order_id',
            onDelete: OnDelete::CASCADE,
            api: true
        );
        $attribute->nullable = false;

        $definition = $attribute->toDefinition();

        static::assertSame([OneToMany::class, 'fromArray'], $definition->getFactory());

        $args = $definition->getArguments();
        static::assertCount(1, $args);
        static::assertIsArray($args[0]);
        static::assertSame('order_line_item', $args[0]['entity']);
        static::assertSame('order_id', $args[0]['ref']);
        static::assertSame(OnDelete::CASCADE->value, $args[0]['onDelete']);
        static::assertTrue($args[0]['api']);
        static::assertFalse($args[0]['nullable']);
    }
}
