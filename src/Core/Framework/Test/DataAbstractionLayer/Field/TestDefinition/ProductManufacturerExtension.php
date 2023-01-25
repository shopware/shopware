<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
class ProductManufacturerExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToOneAssociationField('toOne', 'id', 'product_id', ExtendedProductManufacturerDefinition::class, false)
        );
        $collection->add(
            new OneToManyAssociationField('oneToMany', ExtendedProductManufacturerDefinition::class, 'product_id', 'id')
        );
    }

    public function getDefinitionClass(): string
    {
        return ProductManufacturerDefinition::class;
    }
}
