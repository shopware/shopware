<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Attribute\Simple;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Attribute\AttributeTestFixtures;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ForeignKey::class)]
final class ForeignKeyTest extends TestCase
{
    public function testDefaultColumn(): void
    {
        $attribute = new ForeignKey(entity: AttributeTestFixtures::ENTITY_NAME_PRODUCT);

        $field = $attribute->createField(
            'productId',
            'product_id',
            'order_line_item'
        );

        static::assertInstanceOf(FkField::class, $field);
        static::assertSame('productId', $field->getPropertyName());
        static::assertSame('product_id', $field->getStorageName());
    }

    public function testCustomColumn(): void
    {
        $attribute = new ForeignKey(
            entity: AttributeTestFixtures::ENTITY_NAME_PRODUCT,
            column: 'custom_product_fk'
        );

        $field = $attribute->createField(
            'productId',
            'product_id',
            'order_line_item'
        );

        static::assertInstanceOf(FkField::class, $field);
        static::assertSame('custom_product_fk', $field->getStorageName());
    }

    public function testGetFieldClass(): void
    {
        $attribute = new ForeignKey(entity: AttributeTestFixtures::ENTITY_NAME_PRODUCT);

        static::assertSame(FkField::class, $attribute->getFieldClass());
    }

    public function testFromArray(): void
    {
        $data = [
            'entity' => AttributeTestFixtures::ENTITY_NAME_PRODUCT,
            'api' => false,
            'column' => 'product_fk',
            'nullable' => true,
            'type' => ForeignKey::TYPE,
            'translated' => false,
        ];

        $attribute = ForeignKey::fromArray($data);

        static::assertSame(AttributeTestFixtures::ENTITY_NAME_PRODUCT, $attribute->entity);
        static::assertFalse($attribute->api);
        static::assertSame('product_fk', $attribute->column);
        static::assertTrue($attribute->nullable);
    }

    public function testApiVisibility(): void
    {
        $attribute = new ForeignKey(
            entity: AttributeTestFixtures::ENTITY_NAME_PRODUCT,
            api: true
        );

        $field = $attribute->createField(
            'productId',
            'product_id',
            'order_line_item'
        );

        static::assertInstanceOf(FkField::class, $field);
        static::assertTrue($attribute->api);
    }

    public function testToDefinition(): void
    {
        $attribute = new ForeignKey(
            entity: AttributeTestFixtures::ENTITY_NAME_PRODUCT,
            api: true,
            column: 'custom_fk'
        );
        $attribute->nullable = false;

        $definition = $attribute->toDefinition();

        static::assertSame([ForeignKey::class, 'fromArray'], $definition->getFactory());

        $args = $definition->getArguments();
        static::assertCount(1, $args);
        static::assertIsArray($args[0]);
        static::assertSame(AttributeTestFixtures::ENTITY_NAME_PRODUCT, $args[0]['entity']);
        static::assertTrue($args[0]['api']);
        static::assertSame('custom_fk', $args[0]['column']);
        static::assertFalse($args[0]['nullable']);
    }
}
