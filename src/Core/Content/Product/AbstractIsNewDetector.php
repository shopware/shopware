<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
abstract class AbstractIsNewDetector
{
    abstract public function getDecorated(): AbstractIsNewDetector;

    abstract public function isNew(Entity $product, SalesChannelContext $context): bool;
}
