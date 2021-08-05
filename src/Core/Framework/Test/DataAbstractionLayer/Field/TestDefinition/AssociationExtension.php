<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class AssociationExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField('toMany', ExtendedDefinition::class, 'extendable_id'))
                ->addFlags(new ApiAware())
        );

        $collection->add(
            (new OneToOneAssociationField('toOne', 'id', 'extendable_id', ExtendedDefinition::class, false))
                ->addFlags(new ApiAware())
        );

        $collection->add(
            (new OneToOneAssociationField('toOneWithoutApiAware', 'id', 'extendable_id', ExtendedDefinition::class, false))
                ->removeFlag(ApiAware::class)
        );
    }

    public function getDefinitionClass(): string
    {
        return ExtendableDefinition::class;
    }
}
