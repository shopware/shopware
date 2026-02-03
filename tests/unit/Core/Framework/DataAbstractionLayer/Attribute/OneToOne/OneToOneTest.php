<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Attribute\OneToOne;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OneToOne;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Attribute\AttributeTestFixtures;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OneToOne::class)]
final class OneToOneTest extends TestCase
{
    public function testDefaultColumn(): void
    {
        $attribute = new OneToOne(entity: 'order_customer');

        $field = $attribute->createField(
            'customer',
            'customer',
            'order'
        );

        static::assertInstanceOf(OneToOneAssociationField::class, $field);
        static::assertSame('customer', $field->getPropertyName());
        static::assertSame('customer_id', $field->getStorageName());
    }

    public function testCustomColumn(): void
    {
        $attribute = new OneToOne(
            entity: 'order_customer',
            column: 'custom_fk'
        );

        $field = $attribute->createField(
            'customer',
            'customer',
            'order'
        );

        static::assertInstanceOf(OneToOneAssociationField::class, $field);
        static::assertSame('custom_fk', $field->getStorageName());
    }

    public function testCustomReferenceField(): void
    {
        $attribute = new OneToOne(
            entity: AttributeTestFixtures::ENTITY_NAME_PRODUCT,
            ref: 'uuid'
        );

        $field = $attribute->createField(
            'product',
            'product',
            'order_line_item'
        );

        static::assertInstanceOf(OneToOneAssociationField::class, $field);
        static::assertSame('uuid', $field->getReferenceField());
    }

    public function testGetFieldClass(): void
    {
        $attribute = new OneToOne(entity: AttributeTestFixtures::ENTITY_NAME_PRODUCT);

        static::assertSame(OneToOneAssociationField::class, $attribute->getFieldClass());
    }

    public function testFromArrayWithStringOnDelete(): void
    {
        $data = [
            'entity' => 'order_customer',
            'column' => 'customer_fk',
            'onDelete' => OnDelete::CASCADE->value,
            'ref' => 'id',
            'api' => false,
            'nullable' => true,
            'type' => OneToOne::TYPE,
            'translated' => false,
        ];

        $attribute = OneToOne::fromArray($data);

        static::assertSame('order_customer', $attribute->entity);
        static::assertSame('customer_fk', $attribute->column);
        static::assertSame(OnDelete::CASCADE, $attribute->onDelete);
        static::assertSame('id', $attribute->ref);
        static::assertTrue($attribute->nullable);
    }

    public function testFromArrayWithOnDeleteInstance(): void
    {
        $data = [
            'entity' => 'order_customer',
            'column' => 'custom_fk',
            'onDelete' => OnDelete::RESTRICT,
            'ref' => 'uuid',
            'api' => true,
            'nullable' => false,
            'type' => OneToOne::TYPE,
            'translated' => false,
        ];

        $attribute = OneToOne::fromArray($data);

        static::assertSame('order_customer', $attribute->entity);
        static::assertSame('custom_fk', $attribute->column);
        static::assertSame(OnDelete::RESTRICT, $attribute->onDelete);
        static::assertSame('uuid', $attribute->ref);
        static::assertTrue($attribute->api);
        static::assertFalse($attribute->nullable);
    }

    public function testToDefinition(): void
    {
        $attribute = new OneToOne(
            entity: 'order_customer',
            column: 'custom_fk',
            onDelete: OnDelete::SET_NULL,
            ref: 'uuid',
            api: true
        );
        $attribute->nullable = false;

        $definition = $attribute->toDefinition();

        static::assertSame([OneToOne::class, 'fromArray'], $definition->getFactory());

        $args = $definition->getArguments();
        static::assertCount(1, $args);
        static::assertIsArray($args[0]);
        static::assertSame('order_customer', $args[0]['entity']);
        static::assertSame('custom_fk', $args[0]['column']);
        static::assertSame(OnDelete::SET_NULL->value, $args[0]['onDelete']);
        static::assertSame('uuid', $args[0]['ref']);
        static::assertTrue($args[0]['api']);
        static::assertFalse($args[0]['nullable']);
    }
}
