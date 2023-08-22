<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Stock;

use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class StockLoadRequest
{
    /**
     * @param array<string> $productIds
     */
    public function __construct(public array $productIds)
    {
    }
}
