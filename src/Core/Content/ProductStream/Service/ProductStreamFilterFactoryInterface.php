<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Service;

use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;

interface ProductStreamFilterFactoryInterface
{
    public function createFilter(ProductStreamFilterEntity $filterEntity): Filter;
}
