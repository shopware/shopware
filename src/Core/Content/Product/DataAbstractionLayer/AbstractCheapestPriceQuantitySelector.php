<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Query\QueryBuilder;

abstract class AbstractCheapestPriceQuantitySelector
{
    abstract public function getDecorated(): AbstractCheapestPriceQuantitySelector;

    abstract public function add(QueryBuilder $query): void;
}
