<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

if (Feature::isActive('v6_5_0_0')) {
    abstract class AbstractIsNewDetector
    {
        abstract public function getDecorated(): AbstractIsNewDetector;

        abstract public function isNew(Entity $product, SalesChannelContext $context): bool;
    }
} else {
    abstract class AbstractIsNewDetector
    {
        abstract public function getDecorated(): AbstractIsNewDetector;

        abstract public function isNew(SalesChannelProductEntity $product, SalesChannelContext $context): bool;
    }
}
