<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Attribute\Simple;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ReferenceVersion;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Attribute\AttributeTestFixtures;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ReferenceVersion::class)]
final class ReferenceVersionTest extends TestCase
{
    public function testDefaultColumn(): void
    {
        $attribute = new ReferenceVersion(entity: AttributeTestFixtures::ENTITY_NAME_PRODUCT);

        $field = $attribute->createField(
            'productVersionId',
            'product_version_id',
            'order_line_item'
        );

        static::assertInstanceOf(ReferenceVersionField::class, $field);
        static::assertSame('productVersionId', $field->getPropertyName());
        static::assertSame('product_version_id', $field->getStorageName());
    }

    public function testCustomColumn(): void
    {
        $attribute = new ReferenceVersion(
            entity: AttributeTestFixtures::ENTITY_NAME_PRODUCT,
            column: 'custom_version_id'
        );

        $field = $attribute->createField(
            'productVersionId',
            'product_version_id',
            'order_line_item'
        );

        static::assertInstanceOf(ReferenceVersionField::class, $field);
        static::assertSame('custom_version_id', $field->getStorageName());
    }

    public function testGetFieldClass(): void
    {
        $attribute = new ReferenceVersion(entity: AttributeTestFixtures::ENTITY_NAME_PRODUCT);

        static::assertSame(ReferenceVersionField::class, $attribute->getFieldClass());
    }

    public function testFromArray(): void
    {
        $data = [
            'entity' => AttributeTestFixtures::ENTITY_NAME_PRODUCT,
            'column' => 'product_version_fk',
            'nullable' => false,
            'type' => ReferenceVersion::TYPE,
            'translated' => false,
            'api' => true,
        ];

        $attribute = ReferenceVersion::fromArray($data);

        static::assertSame(AttributeTestFixtures::ENTITY_NAME_PRODUCT, $attribute->entity);
        static::assertSame('product_version_fk', $attribute->column);
        static::assertFalse($attribute->nullable);
        static::assertTrue($attribute->api);
    }

    public function testIsAlwaysApiVisible(): void
    {
        $attribute = new ReferenceVersion(entity: AttributeTestFixtures::ENTITY_NAME_PRODUCT);

        static::assertTrue($attribute->api);
    }

    public function testToDefinition(): void
    {
        $attribute = new ReferenceVersion(
            entity: AttributeTestFixtures::ENTITY_NAME_PRODUCT,
            column: 'custom_version_id'
        );
        $attribute->nullable = false;

        $definition = $attribute->toDefinition();

        static::assertSame([ReferenceVersion::class, 'fromArray'], $definition->getFactory());

        $args = $definition->getArguments();
        static::assertCount(1, $args);
        static::assertIsArray($args[0]);
        static::assertSame(AttributeTestFixtures::ENTITY_NAME_PRODUCT, $args[0]['entity']);
        static::assertSame('custom_version_id', $args[0]['column']);
        static::assertFalse($args[0]['nullable']);
    }
}
