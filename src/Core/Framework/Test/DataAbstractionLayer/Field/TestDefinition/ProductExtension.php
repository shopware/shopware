<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductExtension implements EntityExtensionInterface
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToOneAssociationField('toOne', 'id', 'product_id', ExtendedProductDefinition::class, false)
        );
        $collection->add(
            new OneToManyAssociationField('oneToMany', ExtendedProductDefinition::class, 'product_id', 'id')
        );
        $collection->add(
            new ManyToOneAssociationField('manyToOne', 'id', ExtendedProductDefinition::class, 'product_id')
        );
    }

    public function getDefinitionClass(): string
    {
        return ProductDefinition::class;
    }
}
