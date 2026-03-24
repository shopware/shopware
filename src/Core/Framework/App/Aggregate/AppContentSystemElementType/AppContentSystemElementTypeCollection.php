<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppContentSystemElementType;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<AppContentSystemElementTypeEntity>
 */
#[Package('framework')]
class AppContentSystemElementTypeCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppContentSystemElementTypeEntity::class;
    }
}
