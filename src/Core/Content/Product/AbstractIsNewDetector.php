<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

if (Feature::isActive('v6.5.0.0')) {
    #[Package('inventory')]
    abstract class AbstractIsNewDetector
    {
        abstract public function getDecorated(): AbstractIsNewDetector;

        abstract public function isNew(Entity $product, SalesChannelContext $context): bool;
    }
} else {
    #[Package('inventory')]
    abstract class AbstractIsNewDetector
    {
        abstract public function getDecorated(): AbstractIsNewDetector;

        abstract public function isNew(SalesChannelProductEntity $product, SalesChannelContext $context): bool;
    }
}
