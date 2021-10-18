<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;

abstract class AbstractPropertyGroupSorter
{
    abstract public function getDecorated(): AbstractPropertyGroupSorter;

    abstract public function sort(PropertyGroupOptionCollection $groupOptionCollection): PropertyGroupCollection;
}
