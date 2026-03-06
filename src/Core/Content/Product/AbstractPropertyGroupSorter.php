<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
abstract class AbstractPropertyGroupSorter
{
    abstract public function getDecorated(): AbstractPropertyGroupSorter;

    /**
     * @deprecated tag:v6.8.0 - reason:new-optional-parameter - parameter $localeCode will be added
     *
     * @param EntityCollection<PropertyGroupOptionEntity|PartialEntity> $options
     */
    abstract public function sort(EntityCollection $options /* ?string $localeCode = null */): PropertyGroupCollection;
}
