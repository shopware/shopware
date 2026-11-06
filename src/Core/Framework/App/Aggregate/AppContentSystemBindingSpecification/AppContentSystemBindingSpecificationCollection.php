<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppContentSystemBindingSpecification;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<AppContentSystemBindingSpecificationEntity>
 */
#[Package('framework')]
class AppContentSystemBindingSpecificationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppContentSystemBindingSpecificationEntity::class;
    }
}
