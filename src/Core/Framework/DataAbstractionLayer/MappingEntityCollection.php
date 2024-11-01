<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MappingEntity>
 */
#[Package('core')]
class MappingEntityCollection extends EntityCollection
{
    #[\Override]
    protected function getExpectedClass(): string
    {
        return MappingEntity::class;
    }
}
