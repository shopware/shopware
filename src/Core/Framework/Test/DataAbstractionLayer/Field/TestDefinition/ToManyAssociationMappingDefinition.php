<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

/**
 * @internal
 */
class ToManyAssociationMappingDefinition extends MappingEntityDefinition
{
    final public const ENTITY_NAME = '_test_to_many_association_mapping';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('to_many_id', 'toManyId', ToManyAssociationDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('to_many_dependency_id', 'toManyDependencyId', ToManyAssociationDependencyDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new ManyToOneAssociationField('toMany', 'to_many', ToManyAssociationDefinition::class)),
            (new ManyToOneAssociationField('toManyDependency', 'to_many_dependency', ToManyAssociationDependencyDefinition::class)),
        ]);
    }
}
