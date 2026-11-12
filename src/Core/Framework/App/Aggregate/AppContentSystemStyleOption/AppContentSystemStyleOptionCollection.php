<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppContentSystemStyleOption;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<AppContentSystemStyleOptionEntity>
 */
#[Package('framework')]
class AppContentSystemStyleOptionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppContentSystemStyleOptionEntity::class;
    }
}
