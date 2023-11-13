<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
class ToOneProductExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new FkField('many_to_one_id', 'manyToOneId', ManyToOneProductDefinition::class)
        );
        $collection->add(
            new ManyToOneAssociationField('manyToOne', 'many_to_one_id', ManyToOneProductDefinition::class)
        );
    }

    public function getDefinitionClass(): string
    {
        return ProductDefinition::class;
    }
}
