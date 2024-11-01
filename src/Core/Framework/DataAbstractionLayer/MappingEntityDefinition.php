<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
abstract class MappingEntityDefinition extends EntityDefinition
{
    #[\Override]
    public function getCollectionClass(): string
    {
        return MappingEntityCollection::class;
    }

    #[\Override]
    public function getEntityClass(): string
    {
        return MappingEntity::class;
    }

    protected function getBaseFields(): array
    {
        return [];
    }

    protected function defaultFields(): array
    {
        return [];
    }
}
