<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractIsNewDetector
{
    abstract public function getDecorated(): AbstractIsNewDetector;

    abstract public function isNew(SalesChannelProductEntity $product, SalesChannelContext $context): bool;
}
