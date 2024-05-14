<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Stock;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
final class StockAlteration
{
    public function __construct(
        public readonly string $lineItemId,
        public readonly string $productId,
        public readonly int $quantityBefore,
        public readonly int $newQuantity
    ) {
    }

    public function quantityDelta(): int
    {
        return $this->quantityBefore - $this->newQuantity;
    }
}
