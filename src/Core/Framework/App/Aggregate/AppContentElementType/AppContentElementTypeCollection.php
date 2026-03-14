<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppContentElementType;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<AppContentElementTypeEntity>
 */
#[Package('framework')]
class AppContentElementTypeCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppContentElementTypeEntity::class;
    }
}
