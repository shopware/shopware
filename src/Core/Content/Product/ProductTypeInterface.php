<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
interface ProductTypeInterface
{
    public function getType(): string;

    /**
     * @param list<string> $productIds
     *
     * @return list<string>
     */
    public function getMatchedIds(array $productIds, Context $context): array;

    public function getBehavior(): ProductTypeBehavior;
}
