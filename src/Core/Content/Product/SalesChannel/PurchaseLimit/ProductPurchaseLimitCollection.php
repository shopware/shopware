<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\PurchaseLimit;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @codeCoverageIgnore
 *
 * @extends Collection<ProductPurchaseLimit>
 */
#[Package('inventory')]
class ProductPurchaseLimitCollection extends Collection
{
    protected function getExpectedClass(): string
    {
        return ProductPurchaseLimit::class;
    }
}
