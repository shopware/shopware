<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeManyToManyReverseExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AttributeManyToManyReverseExtension::class)]
class AttributeManyToManyReverseExtensionTest extends TestCase
{
    public function testExtendFieldsAddsReverseAssociationWithMirroredApiAndCascade(): void
    {
        $extension = new AttributeManyToManyReverseExtension(
            'product',
            'attributeEntities',
            'attribute_entity',
            'attribute_entity_product',
            'product_id',
            'attribute_entity_id',
            [AdminApiSource::class],
            OnDelete::CASCADE->value,
        );

        static::assertSame('product', $extension->getEntityName());

        $collection = new FieldCollection();
        $extension->extendFields($collection);

        $field = $collection->first();
        static::assertInstanceOf(ManyToManyAssociationField::class, $field);
        static::assertSame('attributeEntities', $field->getPropertyName());
        static::assertSame('product_id', $field->getMappingLocalColumn());
        static::assertSame('attribute_entity_id', $field->getMappingReferenceColumn());
        static::assertTrue($field->is(ApiAware::class));
        static::assertTrue($field->is(CascadeDelete::class));
    }

    public function testExtendFieldsWithoutApiAndCascadeKeepsDefaultAdminApiAwareOnly(): void
    {
        $extension = new AttributeManyToManyReverseExtension(
            'product',
            'attributeEntities',
            'attribute_entity',
            'attribute_entity_product',
            'product_id',
            'attribute_entity_id',
        );

        $collection = new FieldCollection();
        $extension->extendFields($collection);

        $field = $collection->first();
        static::assertInstanceOf(ManyToManyAssociationField::class, $field);
        // every DAL field is admin api aware by default, mirroring the forward association
        static::assertTrue($field->is(ApiAware::class));
        static::assertFalse($field->is(CascadeDelete::class));
    }

    public function testExtendFieldsWithRestrictOnDelete(): void
    {
        $extension = new AttributeManyToManyReverseExtension(
            'product',
            'attributeEntities',
            'attribute_entity',
            'attribute_entity_product',
            'product_id',
            'attribute_entity_id',
            null,
            OnDelete::RESTRICT->value,
        );

        $collection = new FieldCollection();
        $extension->extendFields($collection);

        $field = $collection->first();
        static::assertInstanceOf(ManyToManyAssociationField::class, $field);
        static::assertTrue($field->is(RestrictDelete::class));
        static::assertFalse($field->is(CascadeDelete::class));
    }
}
