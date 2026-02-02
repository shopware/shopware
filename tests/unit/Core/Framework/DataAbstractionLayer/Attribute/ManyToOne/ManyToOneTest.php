<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Attribute\AttributeTestFixtures;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ManyToOne::class)]
final class ManyToOneTest extends TestCase
{
    public function testDefaultForeignKey(): void
    {
        $attribute = new ManyToOne(entity: AttributeTestFixtures::ENTITY_NAME_PRODUCT);

        $field = $attribute->createField(
            AttributeTestFixtures::PROPERTY_NAME_CURRENCY,
            'currency',
            AttributeTestFixtures::ENTITY_NAME
        );

        static::assertInstanceOf(ManyToOneAssociationField::class, $field);
        static::assertSame(AttributeTestFixtures::PROPERTY_NAME_CURRENCY, $field->getPropertyName());
        static::assertSame('currency_id', $field->getStorageName());
    }

    public function testCustomColumn(): void
    {
        $attribute = new ManyToOne(
            entity: AttributeTestFixtures::ENTITY_NAME_PRODUCT,
            column: 'custom_fk'
        );

        $field = $attribute->createField(
            AttributeTestFixtures::PROPERTY_NAME_CURRENCY,
            'currency',
            AttributeTestFixtures::ENTITY_NAME
        );

        static::assertInstanceOf(ManyToOneAssociationField::class, $field);
        static::assertSame(AttributeTestFixtures::PROPERTY_NAME_CURRENCY, $field->getPropertyName());
        static::assertSame('custom_fk', $field->getStorageName());
    }

    public function testDefaultReferenceField(): void
    {
        $attribute = new ManyToOne(entity: AttributeTestFixtures::ENTITY_NAME_PRODUCT);

        $field = $attribute->createField(
            AttributeTestFixtures::PROPERTY_NAME_CURRENCY,
            'currency',
            AttributeTestFixtures::ENTITY_NAME
        );

        static::assertInstanceOf(ManyToOneAssociationField::class, $field);
        static::assertSame(AttributeTestFixtures::REFERENCE_FIELD_ID, $attribute->ref);
    }

    public function testCustomReferenceField(): void
    {
        $attribute = new ManyToOne(
            entity: AttributeTestFixtures::ENTITY_NAME_PRODUCT,
            ref: 'uuid'
        );

        $field = $attribute->createField(
            AttributeTestFixtures::PROPERTY_NAME_CURRENCY,
            'currency',
            AttributeTestFixtures::ENTITY_NAME
        );

        static::assertInstanceOf(ManyToOneAssociationField::class, $field);
        static::assertSame('uuid', $attribute->ref);
    }

    public function testGetFieldClass(): void
    {
        $attribute = new ManyToOne(entity: AttributeTestFixtures::ENTITY_NAME_PRODUCT);

        static::assertSame(ManyToOneAssociationField::class, $attribute->getFieldClass());
    }

    public function testFromArray(): void
    {
        $data = [
            'entity' => AttributeTestFixtures::ENTITY_NAME_PRODUCT,
            'onDelete' => OnDelete::CASCADE->value,
            'ref' => 'uuid',
            'api' => ['admin', 'store'],
            'column' => 'product_fk',
            'nullable' => true,
            'type' => ManyToOne::TYPE,
            'translated' => false,
        ];

        $attribute = ManyToOne::fromArray($data);

        static::assertSame(AttributeTestFixtures::ENTITY_NAME_PRODUCT, $attribute->entity);
        static::assertSame(OnDelete::CASCADE, $attribute->onDelete);
        static::assertSame('uuid', $attribute->ref);
        static::assertSame(['admin', 'store'], $attribute->api);
        static::assertSame('product_fk', $attribute->column);
        static::assertTrue($attribute->nullable);
    }

    public function testFromArrayWithOnDeleteEnum(): void
    {
        $data = [
            'entity' => AttributeTestFixtures::ENTITY_NAME_PRODUCT,
            'onDelete' => OnDelete::SET_NULL,
            'ref' => 'id',
            'api' => false,
            'column' => null,
            'nullable' => false,
            'type' => ManyToOne::TYPE,
            'translated' => false,
        ];

        $attribute = ManyToOne::fromArray($data);

        static::assertSame(OnDelete::SET_NULL, $attribute->onDelete);
    }

    public function testOnDeleteCascade(): void
    {
        $attribute = new ManyToOne(
            entity: AttributeTestFixtures::ENTITY_NAME_PRODUCT,
            onDelete: OnDelete::CASCADE
        );

        $field = $attribute->createField(
            AttributeTestFixtures::PROPERTY_NAME_PRODUCT,
            'product',
            AttributeTestFixtures::ENTITY_NAME_ORDER
        );

        static::assertInstanceOf(ManyToOneAssociationField::class, $field);
        static::assertSame(OnDelete::CASCADE, $attribute->onDelete);
    }

    public function testToDefinition(): void
    {
        $attribute = new ManyToOne(
            entity: AttributeTestFixtures::ENTITY_NAME_PRODUCT,
            onDelete: OnDelete::SET_NULL,
            ref: 'uuid',
            api: ['admin-api' => true, 'store-api' => false],
            column: 'custom_fk'
        );
        $attribute->nullable = true;

        $definition = $attribute->toDefinition();

        static::assertSame([ManyToOne::class, 'fromArray'], $definition->getFactory());

        $args = $definition->getArguments();
        static::assertCount(1, $args);
        static::assertIsArray($args[0]);
        static::assertSame(AttributeTestFixtures::ENTITY_NAME_PRODUCT, $args[0]['entity']);
        static::assertSame(OnDelete::SET_NULL->value, $args[0]['onDelete']);
        static::assertSame('uuid', $args[0]['ref']);
        static::assertSame(['admin-api' => true, 'store-api' => false], $args[0]['api']);
        static::assertSame('custom_fk', $args[0]['column']);
        static::assertTrue($args[0]['nullable']);
    }
}
