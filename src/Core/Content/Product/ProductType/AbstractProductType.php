<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ProductType;

use Shopware\Core\Content\Product\ProductTypeBehavior;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
abstract class AbstractProductType
{
    abstract public function getType(): string;

    public function getBehavior(): ProductTypeBehavior
    {
        return new ProductTypeBehavior();
    }

    /**
     * @param list<string> $productIds
     *
     * @return list<string>
     */
    abstract public function getMatchedIds(array $productIds, Context $context): array;
}
